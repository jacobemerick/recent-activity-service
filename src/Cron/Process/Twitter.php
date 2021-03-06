<?php

namespace Jacobemerick\LifestreamService\Cron\Process;

use DateTime;
use Exception;
use stdclass;

use Interop\Container\ContainerInterface as Container;
use Jacobemerick\LifestreamService\Cron\CronInterface;
use Jacobemerick\LifestreamService\Model\Twitter as TwitterModel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class Twitter implements CronInterface, LoggerAwareInterface
{

    use LoggerAwareTrait;
    use ProcessTrait;

    /** @var Container */
    protected $container;

    /**
     * @param Container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->logger = new NullLogger;
    }

    public function run()
    {
        try {
            $tweet = $this->fetchTweets($this->container->get('twitterModel'));
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            return;
        }

        array_walk($tweet, [ $this, 'processTweet' ]);
    }

    /**
     * @param TwitterModel $twitterModel
     * @return array
     */
    protected function fetchTweets(TwitterModel $twitterModel)
    {
        return $twitterModel->getTweets();
    }


    /**
     * @param array $tweet
     * @return boolean
     */
    protected function processTweet(array $tweet)
    {
        $event = $this->getEvent(
            $this->container->get('eventModel'),
            'twitter',
            $tweet['id']
        );

        $metadata = $this->getTweetMetadata($tweet);

        $isReply = $this->isTweetReply($tweet);
        if ($isReply && $metadata->favorites < 1 && $metadata->retweets < 1) {
            $this->logger->debug("Skipping tweet, generic reply: {$tweet['id']}");
            return false;
        }

        if (!$event) {
            try {
                $this->insertTweet($tweet, $metadata);
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage());
                return false;
            }

            $this->logger->debug("Added twitter event: {$tweet['id']}");
            return true;
        }

        $isMetadataUpdated = $this->checkMetadataUpdated($event, $metadata);
        if ($isMetadataUpdated) {
            try {
                $this->updateEventMetadata(
                    $this->container->get('eventModel'),
                    $event['id'],
                    $metadata
                );
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage());
                return false;
            }

            $this->logger->debug("Updated twitter event metadata: {$tweet['id']}");
            return true;
        }

        return false;
    }

    /**
     * @param array $tweet
     * @return stdclass
     */
    protected function getTweetMetadata(array $tweet)
    {
        $metadata = json_decode($tweet['metadata']);

        return (object) [
            'favorites' => $metadata->favorite_count,
            'retweets' => $metadata->retweet_count,
        ];
    }

    /**
     * @param array $tweet
     * @return boolean
     */
    protected function isTweetReply(array $tweet)
    {
        $metadata = json_decode($tweet['metadata']);

        if ($metadata->in_reply_to_user_id !== null) {
            return true;
        }
        if (substr($metadata->text, 0, 1) === '@') {
            return true;
        }

        return false;
    }

    /**
     * @param array $tweet
     * @param stdclass $metadata
     * @return boolean
     */
    protected function insertTweet(array $tweet, stdclass $metadata)
    {
        $tweetMetadata = json_decode($tweet['metadata']);

        $description = $this->getDescription($tweetMetadata);
        $descriptionHtml = $this->getDescriptionHtml($tweetMetadata);

        return $this->insertEvent(
            $this->container->get('eventModel'),
            $this->container->get('typeModel'),
            $this->container->get('userModel'),
            $description,
            $descriptionHtml,
            (new DateTime($tweet['datetime'])),
            $metadata,
            'Jacob Emerick',
            'twitter',
            $tweet['id']
        );
    }

    /**
     * @param array $event
     * @param stdclass $metadata
     * @return boolean
     */
    protected function checkMetadataUpdated(array $event, stdclass $metadata)
    {
        $oldMetadata = json_decode($event['metadata']);

        return $oldMetadata != $metadata;
    }

    /**
     * @param stdclass $metadata
     * @return string
     */
    protected function getDescription(stdclass $metadata)
    {
        $message = $metadata->text;
        $entities = $this->getEntities($metadata->entities, [ 'media', 'urls' ]);

        array_walk($entities, function ($entity) use (&$message) {
            $message = (
                mb_substr($message, 0, $entity->indices[0]) .
                "[{$entity->display_url}]" .
                mb_substr($message, $entity->indices[1])
            );
        });

        $message = mb_convert_encoding($message, 'HTML-ENTITIES', 'UTF-8');
        $message = preg_replace('/\s+/', ' ', $message);
        $message = trim($message);

        return sprintf(
            'Tweeted | %s',
            $message
        );
    }

    /**
     * @param stdclass $metadata
     * @return string
     */
    protected function getDescriptionHtml(stdclass $metadata)
    {
        $message = $metadata->text;

        $entityTypes = [
            'hashtags',
            'media',
            'urls',
            'user_mentions',
        ];
        $entities = $this->getEntities($metadata->entities, $entityTypes);

        $self = $this;
        array_walk($entities, function ($entity) use (&$message, $self) {
            $message = (
                mb_substr($message, 0, $entity->indices[0]) .
                $self->getEntityReplacement($entity, $entity->entity_type) .
                mb_substr($message, $entity->indices[1])
            );
        });

        $message = mb_convert_encoding($message, 'HTML-ENTITIES', 'UTF-8');
        $message = str_replace([ "\r\n", "\r", "\n" ], '<br />', $message);
        $message = "<p>{$message}</p>";

        return $message;
    }

    /**
     * @param stdclass $tweetEntities
     * @param array $entityTypes
     * @return array
     */
    protected function getEntities(stdclass $tweetEntities, array $entityTypes)
    {
        $entities = [];
        array_walk($entityTypes, function ($entityType) use ($tweetEntities, &$entities) {
            if (isset($tweetEntities->{$entityType})) {
                $taggedEntities = array_map(function ($entity) use ($entityType) {
                    $entity->entity_type = $entityType;
                    return $entity;
                }, $tweetEntities->{$entityType});
                $entities = array_merge($entities, $taggedEntities);
            }
        });

        usort($entities, function ($entityA, $entityB) {
            return $entityA->indices[0] < $entityB->indices[0];
        });

        return $entities;
    }

    /**
     * @param stdclass $entity
     * @param string $entityType
     * @return string
     */
    protected function getEntityReplacement(stdclass $entity, $entityType)
    {
        $replacement = '';

        switch ($entityType) {
            case 'hashtags':
                $replacement = sprintf(
                    '<a href="https://twitter.com/hashtag/%s?src=hash" rel="nofollow" target="_blank">#%s</a>',
                    $entity->text,
                    $entity->text
                );
                break;
            case 'media':
                $replacement = sprintf(
                    '<img src="%s:%s" alt="Twitter Media | %s" height="%d" width="%d" />',
                    $entity->media_url_https,
                    'large',
                    $entity->display_url,
                    $entity->sizes->large->h,
                    $entity->sizes->large->w
                );
                break;
            case 'urls':
                $replacement = sprintf(
                    '<a href="%s" rel="nofollow" target="_blank" title="%s">%s</a>',
                    $entity->url,
                    $entity->expanded_url,
                    $entity->display_url
                );
                break;
            case 'user_mentions':
                $replacement = sprintf(
                    '<a href="https://twitter.com/%s" rel="nofollow" target="_blank" title="Twitter | %s">@%s</a>',
                    $entity->screen_name,
                    $entity->name,
                    $entity->screen_name
                );
                break;
            default:
                throw new Exception("Cannot determine an acceptable replacement for {$entityType}");
                break;
        }

        return $replacement;
    }
}
