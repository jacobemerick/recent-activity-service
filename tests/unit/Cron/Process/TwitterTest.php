<?php

namespace Jacobemerick\LifestreamService\Cron\Process;

use DateTime;
use Exception;
use ReflectionClass;
use stdclass;

use PHPUnit\Framework\TestCase;

use Interop\Container\ContainerInterface as Container;
use Jacobemerick\LifestreamService\Cron\CronInterface;
use Jacobemerick\LifestreamService\Model\Twitter as TwitterModel;
use Jacobemerick\LifestreamService\Model\Event as EventModel;
use Jacobemerick\LifestreamService\Model\Type as TypeModel;
use Jacobemerick\LifestreamService\Model\User as UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

class TwitterTest extends TestCase
{

    public function testIsInstanceOfTwitter()
    {
        $mockContainer = $this->createMock(Container::class);
        $cron = new Twitter($mockContainer);

        $this->assertInstanceOf(Twitter::class, $cron);
    }

    public function testIsInstanceOfCronInterface()
    {
        $mockContainer = $this->createMock(Container::class);
        $cron = new Twitter($mockContainer);

        $this->assertInstanceOf(CronInterface::class, $cron);
    }

    public function testIsInstanceOfLoggerAwareInterface()
    {
        $mockContainer = $this->createMock(Container::class);
        $cron = new Twitter($mockContainer);

        $this->assertInstanceOf(LoggerAwareInterface::class, $cron);
    }

    public function testConstructSetsContainer()
    {
        $mockContainer = $this->createMock(Container::class);
        $cron = new Twitter($mockContainer);

        $this->assertAttributeSame($mockContainer, 'container', $cron);
    }

    public function testConstructSetsNullLogger()
    {
        $mockContainer = $this->createMock(Container::class);
        $cron = new Twitter($mockContainer);

        $this->assertAttributeInstanceOf(NullLogger::class, 'logger', $cron);
    }

    public function testRunFetchesTweet()
    {
        $mockTwitterModel = $this->createMock(TwitterModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'twitterModel', $mockTwitterModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'fetchTweets',
                'processTweet',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('fetchTweets')
            ->with($mockTwitterModel)
            ->willReturn([]);
        $twitter->expects($this->never())
            ->method('processTweet');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $twitter->run();
    }

    public function testRunLogsThrownExceptionsFromFetchTweets()
    {
        $mockExceptionMessage = 'Failed to fetch tweets';
        $mockException = new Exception($mockExceptionMessage);

        $mockTwitterModel = $this->createMock(TwitterModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'twitterModel', $mockTwitterModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->once())
            ->method('error')
            ->with($this->equalTo($mockExceptionMessage));

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'fetchTweets',
                'processTweet',
            ])
            ->getMock();
        $twitter->method('fetchTweets')
            ->will($this->throwException($mockException));
        $twitter->expects($this->never())
            ->method('processTweet');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $twitter->run();
    }

    public function testRunProcessTweetForEachTweet()
    {
        $tweets = [
            [ 'id' => 1 ],
            [ 'id' => 2 ],
        ];

        $mockTwitterModel = $this->createMock(TwitterModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'twitterModel', $mockTwitterModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'fetchTweets',
                'processTweet',
            ])
            ->getMock();
        $twitter->method('fetchTweets')
            ->willReturn($tweets);
        $twitter->expects($this->exactly(count($tweets)))
            ->method('processTweet')
            ->withConsecutive(
                [ $tweets[0] ],
                [ $tweets[1] ]
            );

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $twitter->run();
    }

    public function testFetchTweetsPullsFromModel()
    {
        $mockTwitterModel = $this->createMock(TwitterModel::class);
        $mockTwitterModel->expects($this->once())
            ->method('getTweets');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedFetchTweetsMethod = $reflectedTwitter->getMethod('fetchTweets');
        $reflectedFetchTweetsMethod->setAccessible(true);

        $reflectedFetchTweetsMethod->invokeArgs($twitter, [
            $mockTwitterModel,
        ]);
    }

    public function testFetchTweetsReturnsTweets()
    {
        $tweets = [
            [ 'id' => 1 ],
            [ 'id' => 2 ],
        ];

        $mockTwitterModel = $this->createMock(TwitterModel::class);
        $mockTwitterModel->method('getTweets')
            ->willReturn($tweets);

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedFetchTweetsMethod = $reflectedTwitter->getMethod('fetchTweets');
        $reflectedFetchTweetsMethod->setAccessible(true);

        $result = $reflectedFetchTweetsMethod->invokeArgs($twitter, [
            $mockTwitterModel,
        ]);

        $this->assertEquals($tweets, $result);
    }

    public function testProcessTweetGetsEvent()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(false);
        $twitter->expects($this->once())
            ->method('getEvent')
            ->with(
                $mockEventModel,
                'twitter',
                $tweet['id']
            )
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetGetsTweetMetadata()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(false);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->expects($this->once())
            ->method('getTweetMetadata')
            ->with($tweet)
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetChecksIfTweetIsReply()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(false);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->expects($this->once())
            ->method('isTweetReply')
            ->with($tweet)
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetSkipsTweetIfReplyAndHasNoInteraction()
    {
        $tweet = [
            'id' => 1,
        ];

        $tweetMetadata = (object) [
            'favorites' => 0,
            'retweets' => 0,
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with('Skipping tweet, generic reply: 1');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->expects($this->never())
            ->method('checkMetadataUpdated');
        $twitter->method('getEvent')
            ->willReturn(false);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(true);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $result = $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertFalse($result);
    }

    public function testProcessTweetContinuesIfReplyAndHasFavorites()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'favorites' => 1,
            'retweets' => 0,
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('checkMetadataUpdated')
            ->willReturn(false);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(true);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetContinuesIfReplyAndHasRetweets()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'favorites' => 0,
            'retweets' => 1,
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('checkMetadataUpdated')
            ->willReturn(false);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(true);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetInsertsTweetIfEventNotExists()
    {
        $tweet = [
            'id' => 1,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with('Added twitter event: 1');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->expects($this->never())
            ->method('checkMetadataUpdated');
        $twitter->method('getEvent')
            ->willReturn(false);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->once())
            ->method('insertTweet')
            ->with(
                $tweet,
                $tweetMetadata
            );
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetFailsIfInsertTweetFails()
    {
        $mockExceptionMessage = 'Failed to insert tweet';
        $mockException = new Exception($mockExceptionMessage);

        $tweet = [
            'id' => 1,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->once())
            ->method('error')
            ->with($this->equalTo($mockExceptionMessage));

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->expects($this->never())
            ->method('checkMetadataUpdated');
        $twitter->method('getEvent')
            ->willReturn(false);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->method('insertTweet')
            ->will($this->throwException($mockException));
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $result = $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertFalse($result);
    }

    public function testProcessTweetReturnsTrueIfInsertTweetSucceeds()
    {
        $tweet = [
            'id' => 1,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(false);
        $twitter->method('getEvent')
            ->willReturn(false);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $result = $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertTrue($result);
    }

    public function testProcessTweetChecksMetadataUpdated()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('checkMetadataUpdated')
            ->with(
                $event,
                $tweetMetadata
            )
            ->willReturn(false);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetUpdatesTweetIfMetadataUpdated()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with('Updated twitter event metadata: 1');
        $mockLogger->expects($this->never())
            ->method('error');

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(true);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->once())
            ->method('updateEventMetadata')
            ->with(
                $mockEventModel,
                $event['id'],
                $tweetMetadata
            );

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);
    }

    public function testProcessTweetFailsIfUpdateMetadataFails()
    {
        $mockExceptionMessage = 'Failed to update tweet';
        $mockException = new Exception($mockExceptionMessage);

        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->never())
            ->method('debug');
        $mockLogger->expects($this->once())
            ->method('error')
            ->with($this->equalTo($mockExceptionMessage));

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(true);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->method('updateEventMetadata')
            ->will($this->throwException($mockException));

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $result = $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertFalse($result);
    }

    public function testProcessTweetReturnsTrueIfUpdateMetadataSucceeds()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(true);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(false);

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $result = $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertTrue($result);
    }

    public function testProcessTweetReturnsFalseIfNoChangeMade()
    {
        $tweet = [
            'id' => 1,
        ];

        $event = [
            'id' => 2,
        ];

        $tweetMetadata = (object) [
            'some key' => 'some value',
        ];

        $mockEventModel = $this->createMock(EventModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
            ]));

        $mockLogger = $this->createMock(Logger::class);

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'checkMetadataUpdated',
                'getEvent',
                'getTweetMetadata',
                'insertTweet',
                'isTweetReply',
                'updateEventMetadata',
            ])
            ->getMock();
        $twitter->method('checkMetadataUpdated')
            ->willReturn(false);
        $twitter->method('getEvent')
            ->willReturn($event);
        $twitter->method('getTweetMetadata')
            ->willReturn($tweetMetadata);
        $twitter->expects($this->never())
            ->method('insertTweet');
        $twitter->method('isTweetReply')
            ->willReturn(false);
        $twitter->expects($this->never())
            ->method('updateEventMetadata');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedLoggerProperty = $reflectedTwitter->getProperty('logger');
        $reflectedLoggerProperty->setAccessible(true);
        $reflectedLoggerProperty->setValue($twitter, $mockLogger);

        $reflectedProcessTweetMethod = $reflectedTwitter->getMethod('processTweet');
        $reflectedProcessTweetMethod->setAccessible(true);

        $result = $reflectedProcessTweetMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertFalse($result);
    }

    public function testGetTweetMetadataFormatsMetadata()
    {
        $metadata = (object) [
            'favorite_count' => 2,
            'retweet_count' => 3,
        ];

        $expectedMetadata = (object) [
            'favorites' => 2,
            'retweets' => 3,
        ];

        $tweet = [ 'metadata' => json_encode($metadata) ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetTweetMetadataMethod = $reflectedTwitter->getMethod('getTweetMetadata');
        $reflectedGetTweetMetadataMethod->setAccessible(true);

        $result = $reflectedGetTweetMetadataMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertEquals($expectedMetadata, $result);
    }

    public function testIsTweetReplyReturnsTrueIfMetadataIsReply()
    {
        $metadata = [
            'in_reply_to_user_id' => 123,
            'text' => 'some text',
        ];

        $tweet = [
            'metadata' => json_encode($metadata),
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedIsTweetReplyMethod = $reflectedTwitter->getMethod('isTweetReply');
        $reflectedIsTweetReplyMethod->setAccessible(true);

        $result = $reflectedIsTweetReplyMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertTrue($result);
    }

    public function testIsTweetReplyReturnsTrueIfTextStartsWithSnail()
    {
        $metadata = [
            'in_reply_to_user_id' => null,
            'text' => '@user some text',
        ];

        $tweet = [
            'metadata' => json_encode($metadata),
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedIsTweetReplyMethod = $reflectedTwitter->getMethod('isTweetReply');
        $reflectedIsTweetReplyMethod->setAccessible(true);

        $result = $reflectedIsTweetReplyMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertTrue($result);
    }

    public function testIsTweetReplyReturnsFalseIfNotReply()
    {
        $metadata = [
            'in_reply_to_user_id' => null,
            'text' => 'some text',
        ];

        $tweet = [
            'metadata' => json_encode($metadata),
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedIsTweetReplyMethod = $reflectedTwitter->getMethod('isTweetReply');
        $reflectedIsTweetReplyMethod->setAccessible(true);

        $result = $reflectedIsTweetReplyMethod->invokeArgs($twitter, [
            $tweet,
        ]);

        $this->assertFalse($result);
    }

    public function testInsertTweetGetsDescription()
    {
        $metadata = (object) [
            'some key' => 'some value',
        ];

        $tweet = [
            'id' => 1,
            'datetime' => '2016-06-30 12:00:00',
            'metadata' => json_encode($metadata),
        ];

        $mockEventModel = $this->createMock(EventModel::class);
        $mockTypeModel = $this->createMock(TypeModel::class);
        $mockUserModel = $this->createMock(UserModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
                [ 'typeModel', $mockTypeModel ],
                [ 'userModel', $mockUserModel ],
            ]));

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getDescription',
                'getDescriptionHtml',
                'insertEvent',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('getDescription')
            ->with($this->equalTo($metadata));

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedInsertTweetMethod = $reflectedTwitter->getMethod('insertTweet');
        $reflectedInsertTweetMethod->setAccessible(true);

        $reflectedInsertTweetMethod->invokeArgs($twitter, [
            $tweet,
            $metadata,
        ]);
    }

    public function testInsertTweetGetsDescriptionHtml()
    {
        $metadata = (object) [
            'some key' => 'some value',
        ];

        $tweet = [
            'id' => 1,
            'datetime' => '2016-06-30 12:00:00',
            'metadata' => json_encode($metadata),
        ];

        $mockEventModel = $this->createMock(EventModel::class);
        $mockTypeModel = $this->createMock(TypeModel::class);
        $mockUserModel = $this->createMock(UserModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
                [ 'typeModel', $mockTypeModel ],
                [ 'userModel', $mockUserModel ],
            ]));

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getDescription',
                'getDescriptionHtml',
                'insertEvent',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('getDescriptionHtml')
            ->with($this->equalTo($metadata));

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedInsertTweetMethod = $reflectedTwitter->getMethod('insertTweet');
        $reflectedInsertTweetMethod->setAccessible(true);

        $reflectedInsertTweetMethod->invokeArgs($twitter, [
            $tweet,
            $metadata,
        ]);
    }

    public function testInsertTweetSendsParametersToInsertEvent()
    {
        $metadata = (object) [
            'some key' => 'some value',
        ];

        $datetime = '2016-06-30 12:00:00';

        $tweet = [
            'id' => 1,
            'datetime' => $datetime,
            'metadata' => json_encode($metadata),
        ];

        $description = 'some description';
        $descriptionHtml = '<p>some description</p>';

        $expectedResponse = true;

        $mockEventModel = $this->createMock(EventModel::class);
        $mockTypeModel = $this->createMock(TypeModel::class);
        $mockUserModel = $this->createMock(UserModel::class);

        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('get')
            ->will($this->returnValueMap([
                [ 'eventModel', $mockEventModel ],
                [ 'typeModel', $mockTypeModel ],
                [ 'userModel', $mockUserModel ],
            ]));

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getDescription',
                'getDescriptionHtml',
                'insertEvent',
            ])
            ->getMock();
        $twitter->method('getDescription')
            ->willReturn($description);
        $twitter->method('getDescriptionHtml')
            ->willReturn($descriptionHtml);
        $twitter->expects($this->once())
            ->method('insertEvent')
            ->with(
                $this->equalTo($mockEventModel),
                $this->equalTo($mockTypeModel),
                $this->equalTo($mockUserModel),
                $this->equalTo($description),
                $this->equalTo($descriptionHtml),
                $this->callback(function ($param) use ($datetime) {
                    return $param->format('Y-m-d H:i:s') === $datetime;
                }),
                $this->equalTo($metadata),
                $this->equalTo('Jacob Emerick'),
                $this->equalTo('twitter'),
                $this->equalTo($tweet['id'])
            )
            ->willReturn($expectedResponse);

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedContainerProperty = $reflectedTwitter->getProperty('container');
        $reflectedContainerProperty->setAccessible(true);
        $reflectedContainerProperty->setValue($twitter, $mockContainer);

        $reflectedInsertTweetMethod = $reflectedTwitter->getMethod('insertTweet');
        $reflectedInsertTweetMethod->setAccessible(true);

        $result = $reflectedInsertTweetMethod->invokeArgs($twitter, [
            $tweet,
            $metadata,
        ]);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testCheckMetadataUpdatedReturnsTrueIfDifferent()
    {
        $metadata = (object) [
            'some key' => 'some value',
        ];

        $newMetadata = (object) [
            'some key' => 'some other value',
        ];

        $event = [ 'metadata' => json_encode($metadata) ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedCheckMetadataUpdatedMethod = $reflectedTwitter->getMethod('checkMetadataUpdated');
        $reflectedCheckMetadataUpdatedMethod->setAccessible(true);

        $result = $reflectedCheckMetadataUpdatedMethod->invokeArgs($twitter, [
            $event,
            $newMetadata,
        ]);

        $this->assertTrue($result);
    }

    public function testCheckMetadataUpdatedReturnsFalseIfSame()
    {
        $metadata = (object) [
            'some key' => 'some value',
        ];

        $event = [ 'metadata' => json_encode($metadata) ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedCheckMetadataUpdatedMethod = $reflectedTwitter->getMethod('checkMetadataUpdated');
        $reflectedCheckMetadataUpdatedMethod->setAccessible(true);

        $result = $reflectedCheckMetadataUpdatedMethod->invokeArgs($twitter, [
            $event,
            $metadata,
        ]);

        $this->assertFalse($result);
    }

    public function testGetDescriptionFetchesEntities()
    {
        $entities = (object) [
            'some key' => 'some value',
        ];

        $metadata = (object) [
            'entities' => $entities,
            'text' => 'some text',
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('getEntities')
            ->with(
                $this->equalTo($entities),
                $this->equalTo([ 'media', 'urls' ])
            )
            ->willReturn([]);

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionMethod = $reflectedTwitter->getMethod('getDescription');
        $reflectedGetDescriptionMethod->setAccessible(true);

        $reflectedGetDescriptionMethod->invokeArgs($twitter, [
            $metadata,
        ]);
    }

    public function testGetDescriptionLoopsReplacesEntitiesInText()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => 'the quick brown dog jumps over the lazy fox',
        ];

        $entities = [
            (object) [
                'indices' => [ 40, 43 ],
                'display_url' => 'dog',
            ],
            (object) [
                'indices' => [ 16, 19 ],
                'display_url' => 'fox',
            ],
        ];

        $expectedDescription = 'Tweeted | the quick brown [fox] jumps over the lazy [dog]';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn($entities);

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionMethod = $reflectedTwitter->getMethod('getDescription');
        $reflectedGetDescriptionMethod->setAccessible(true);

        $result = $reflectedGetDescriptionMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescription, $result);
    }

    public function testGetDescriptionConvertsHtmlEntities()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => 'some text…',
        ];

        $expectedDescription = 'Tweeted | some text&hellip;';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn([]);

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionMethod = $reflectedTwitter->getMethod('getDescription');
        $reflectedGetDescriptionMethod->setAccessible(true);

        $result = $reflectedGetDescriptionMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescription, $result);
    }

    public function testGetDescriptionReplacesConsecutiveWhitespaceToSingle()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => "some  spaced\nout\r\ntext",
        ];

        $expectedDescription = 'Tweeted | some spaced out text';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn([]);

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionMethod = $reflectedTwitter->getMethod('getDescription');
        $reflectedGetDescriptionMethod->setAccessible(true);

        $result = $reflectedGetDescriptionMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescription, $result);
    }

    public function testGetDescriptionTrimsTweet()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => 'some text with trailing whitespace ',
        ];

        $expectedDescription = 'Tweeted | some text with trailing whitespace';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn([]);

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionMethod = $reflectedTwitter->getMethod('getDescription');
        $reflectedGetDescriptionMethod->setAccessible(true);

        $result = $reflectedGetDescriptionMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescription, $result);
    }

    public function testGetDescriptionHtmlFetchesEntities()
    {
        $entities = (object) [
            'some key' => 'some value',
        ];

        $metadata = (object) [
            'entities' => $entities,
            'text' => 'some text',
        ];

        $entityTypes = [
            'hashtags',
            'media',
            'urls',
            'user_mentions',
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
                'getEntityReplacement',
            ])
            ->getMock();
        $twitter->expects($this->once())
            ->method('getEntities')
            ->with(
                $this->equalTo($entities),
                $this->equalTo($entityTypes)
            )
            ->willReturn([]);
        $twitter->expects($this->never())
            ->method('getEntityReplacement');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionHtmlMethod = $reflectedTwitter->getMethod('getDescriptionHtml');
        $reflectedGetDescriptionHtmlMethod->setAccessible(true);

        $reflectedGetDescriptionHtmlMethod->invokeArgs($twitter, [
            $metadata,
        ]);
    }

    public function testGetDescriptionHtmlLoopsReplacesEntitiesInText()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => 'the quick brown dog jumps over the lazy fox',
        ];

        $entities = [
            (object) [
                'indices' => [ 40, 43 ],
                'entity_type' => 'one',
            ],
            (object) [
                'indices' => [ 16, 19 ],
                'entity_type' => 'two',
            ],
        ];

        $expectedDescriptionHtml = '<p>the quick brown one-cat jumps over the lazy two-cat</p>';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
                'getEntityReplacement',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn($entities);
        $twitter->expects($this->exactly(2))
            ->method('getEntityReplacement')
            ->withConsecutive(
                [ $entities[0], $entities[0]->entity_type ],
                [ $entities[1], $entities[1]->entity_type ]
            )
            ->will($this->onConsecutiveCalls('two-cat', 'one-cat'));

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionHtmlMethod = $reflectedTwitter->getMethod('getDescriptionHtml');
        $reflectedGetDescriptionHtmlMethod->setAccessible(true);

        $result = $reflectedGetDescriptionHtmlMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescriptionHtml, $result);
    }

    public function testGetDescriptionHtmlConvertsHtmlEntities()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => 'some text…',
        ];

        $expectedDescriptionHtml = '<p>some text&hellip;</p>';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
                'getEntityReplacement',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn([]);
        $twitter->expects($this->never())
            ->method('getEntityReplacement');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionHtmlMethod = $reflectedTwitter->getMethod('getDescriptionHtml');
        $reflectedGetDescriptionHtmlMethod->setAccessible(true);

        $result = $reflectedGetDescriptionHtmlMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescriptionHtml, $result);
    }

    public function testGetDescriptionHtmlConversNewLinesToBreaks()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => "some spaced\nout\r\ntext",
        ];

        $expectedDescriptionHtml = '<p>some spaced<br />out<br />text</p>';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
                'getEntityReplacement',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn([]);
        $twitter->expects($this->never())
            ->method('getEntityReplacement');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionHtmlMethod = $reflectedTwitter->getMethod('getDescriptionHtml');
        $reflectedGetDescriptionHtmlMethod->setAccessible(true);

        $result = $reflectedGetDescriptionHtmlMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescriptionHtml, $result);
    }

    public function testGetDescriptionHtmlWrapsWithParagraphTags()
    {
        $metadata = (object) [
            'entities' => (object) [],
            'text' => 'some text',
        ];

        $expectedDescriptionHtml = '<p>some text</p>';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getEntities',
                'getEntityReplacement',
            ])
            ->getMock();
        $twitter->method('getEntities')
            ->willReturn([]);
        $twitter->expects($this->never())
            ->method('getEntityReplacement');

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetDescriptionHtmlMethod = $reflectedTwitter->getMethod('getDescriptionHtml');
        $reflectedGetDescriptionHtmlMethod->setAccessible(true);

        $result = $reflectedGetDescriptionHtmlMethod->invokeArgs($twitter, [
            $metadata,
        ]);

        $this->assertEquals($expectedDescriptionHtml, $result);
    }

    public function testGetEntitiesHandlesSingleEntity()
    {
        $tweetEntities = (object) [
            'some key' => [
                (object) [
                    'name' => 'wanted',
                    'indices' => [ 0, 0 ],
                ],
            ],
            'some other key' => [
                (object) [
                    'name' => 'not wanted',
                    'indices' => [ 0, 0 ],
                ],
            ],
        ];

        $entityTypes = [
            'some key',
        ];

        $expectedEntities = [
            (object) [
                'name' => 'wanted',
                'indices' => [ 0, 0 ],
                'entity_type' => 'some key',
            ],
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntitiesMethod = $reflectedTwitter->getMethod('getEntities');
        $reflectedGetEntitiesMethod->setAccessible(true);

        $result = $reflectedGetEntitiesMethod->invokeArgs($twitter, [
            $tweetEntities,
            $entityTypes,
        ]);

        $this->assertEquals($expectedEntities, $result);
    }

    public function testGetEntitiesHandlesEmptyEntities()
    {
        $tweetEntities = (object) [
            'some other key' => [
                (object) [
                    'name' => 'not wanted',
                    'indices' => [ 0, 0 ],
                ],
            ],
        ];

        $entityTypes = [
            'some key',
        ];

        $expectedEntities = [];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntitiesMethod = $reflectedTwitter->getMethod('getEntities');
        $reflectedGetEntitiesMethod->setAccessible(true);

        $result = $reflectedGetEntitiesMethod->invokeArgs($twitter, [
            $tweetEntities,
            $entityTypes,
        ]);

        $this->assertEquals($expectedEntities, $result);
    }

    public function testGetEntitiesHandlesMultipleEntities()
    {
        $tweetEntities = (object) [
            'some key' => [
                (object) [
                    'name' => 'wanted',
                    'indices' => [ 0, 0 ],
                ],
                (object) [
                    'name' => 'also wanted',
                    'indices' => [ 0, 0 ],
                ],
            ],
            'some other wanted key' => [
                (object) [
                    'name' => 'another wanted',
                    'indices' => [ 0, 0 ],
                ],
            ],
            'some other unwanted key' => [
                (object) [
                    'name' => 'unwanted',
                    'indices' => [ 0, 0 ],
                ],
            ],
        ];

        $entityTypes = [
            'some key',
            'some other wanted key',
        ];

        $expectedEntities = [
            (object) [
                'name' => 'wanted',
                'indices' => [ 0, 0 ],
                'entity_type' => 'some key',
            ],
            (object) [
                'name' => 'also wanted',
                'indices' => [ 0, 0 ],
                'entity_type' => 'some key',
            ],
            (object) [
                'name' => 'another wanted',
                'indices' => [ 0, 0 ],
                'entity_type' => 'some other wanted key',
            ],
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntitiesMethod = $reflectedTwitter->getMethod('getEntities');
        $reflectedGetEntitiesMethod->setAccessible(true);

        $result = $reflectedGetEntitiesMethod->invokeArgs($twitter, [
            $tweetEntities,
            $entityTypes,
        ]);

        $this->assertEquals($expectedEntities, $result);
    }

    public function testGetEntitiesSortsDescending()
    {
        $tweetEntities = (object) [
            'some key' => [
                (object) [
                    'name' => 'second',
                    'indices' => [ 1, 0 ],
                ],
            ],
            'some other wanted key' => [
                (object) [
                    'name' => 'first',
                    'indices' => [ 2, 0 ],
                ],
            ],
        ];

        $entityTypes = [
            'some key',
            'some other wanted key',
        ];

        $expectedEntities = [
            (object) [
                'name' => 'first',
                'indices' => [ 2, 0 ],
                'entity_type' => 'some other wanted key',
            ],
            (object) [
                'name' => 'second',
                'indices' => [ 1, 0 ],
                'entity_type' => 'some key',
            ],
        ];

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntitiesMethod = $reflectedTwitter->getMethod('getEntities');
        $reflectedGetEntitiesMethod->setAccessible(true);

        $result = $reflectedGetEntitiesMethod->invokeArgs($twitter, [
            $tweetEntities,
            $entityTypes,
        ]);

        $this->assertEquals($expectedEntities, $result);
    }

    public function testGetEntityReplacementHandlesHashtags()
    {
        $entity = (object) [
            'text' => 'awesome',
        ];
        $entityType = 'hashtags';

        $expectedReplacement = '';
        $expectedReplacement .= '<a href="https://twitter.com/hashtag/awesome?src=hash" rel="nofollow"';
        $expectedReplacement .= ' target="_blank">#awesome</a>';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntityReplacementMethod = $reflectedTwitter->getMethod('getEntityReplacement');
        $reflectedGetEntityReplacementMethod->setAccessible(true);

        $result = $reflectedGetEntityReplacementMethod->invokeArgs($twitter, [
            $entity,
            $entityType,
        ]);

        $this->assertEquals($expectedReplacement, $result);
    }

    public function testGetEntityReplacementHandlesMedia()
    {
        $entity = (object) [
            'media_url_https' => 'secure_media_url',
            'display_url' => 'pretty_url',
            'sizes' => (object) [
                'large' => (object) [
                    'h' => 480,
                    'w' => 640,
                ],
            ],
        ];
        $entityType = 'media';

        $expectedReplacement = '';
        $expectedReplacement .= '<img src="secure_media_url:large" alt="Twitter Media | pretty_url"';
        $expectedReplacement .= ' height="480" width="640" />';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntityReplacementMethod = $reflectedTwitter->getMethod('getEntityReplacement');
        $reflectedGetEntityReplacementMethod->setAccessible(true);

        $result = $reflectedGetEntityReplacementMethod->invokeArgs($twitter, [
            $entity,
            $entityType,
        ]);

        $this->assertEquals($expectedReplacement, $result);
    }

    public function testGetEntityReplacementHandlesUrls()
    {
        $entity = (object) [
            'url' => 'base_url',
            'expanded_url' => 'longer_url',
            'display_url' => 'pretty_url',
        ];
        $entityType = 'urls';

        $expectedReplacement = '<a href="base_url" rel="nofollow" target="_blank" title="longer_url">pretty_url</a>';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntityReplacementMethod = $reflectedTwitter->getMethod('getEntityReplacement');
        $reflectedGetEntityReplacementMethod->setAccessible(true);

        $result = $reflectedGetEntityReplacementMethod->invokeArgs($twitter, [
            $entity,
            $entityType,
        ]);

        $this->assertEquals($expectedReplacement, $result);
    }

    public function testGetEntityReplacementHandlesUserMentions()
    {
        $entity = (object) [
            'screen_name' => 'screen',
            'name' => 'some name',
        ];
        $entityType = 'user_mentions';

        $expectedReplacement = '';
        $expectedReplacement .= '<a href="https://twitter.com/screen" rel="nofollow" target="_blank"';
        $expectedReplacement .= ' title="Twitter | some name">@screen</a>';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntityReplacementMethod = $reflectedTwitter->getMethod('getEntityReplacement');
        $reflectedGetEntityReplacementMethod->setAccessible(true);

        $result = $reflectedGetEntityReplacementMethod->invokeArgs($twitter, [
            $entity,
            $entityType,
        ]);

        $this->assertEquals($expectedReplacement, $result);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Cannot determine an acceptable replacement for some type
     */
    public function testGetEntityReplacementThrowsExceptionOnRecognizedEntityType()
    {
        $entity = new stdclass();
        $entityType = 'some type';

        $twitter = $this->getMockBuilder(Twitter::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $reflectedTwitter = new ReflectionClass(Twitter::class);

        $reflectedGetEntityReplacementMethod = $reflectedTwitter->getMethod('getEntityReplacement');
        $reflectedGetEntityReplacementMethod->setAccessible(true);

        $reflectedGetEntityReplacementMethod->invokeArgs($twitter, [
            $entity,
            $entityType,
        ]);
    }
}
