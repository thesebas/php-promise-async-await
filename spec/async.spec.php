<?php

namespace thesebas\promise;

use Exception;
use Kahlan\Arg;
use Kahlan\Plugin\Double;
use React\EventLoop\Factory;
use React\Promise\Promise;
use function React\Promise\all;
use function React\Promise\race;
use function React\Promise\reject;
use function React\Promise\resolve;

describe('async', function () {
    describe('with simple promise', function () {
        it('should return promise with value', function () {
            asyncRun(function () {
                return yield resolve('val');
            })->then(function ($res) {
                $this->result = $res;
            });
            expect($this->result)->toBe('val');
        });
        it('should not swallow rejections/exceptions when not last', function () {
            $onResolve = Double::instance(['magicMethods' => true]);
            $onReject = Double::instance(['magicMethods' => true]);
            expect($onResolve)->toReceive('__invoke')->with("done");
            expect($onReject)->not->toReceive('__invoke');

            $f = function () {
                try {
                    yield reject();
                } catch (Exception $e) {
                }
                $res = yield resolve("ok");
                expect($res)->toBe("ok");
                return "done";
            };
            asyncRun($f)->then($onResolve, $onReject);
        });

        it('should resolve if catched exception is last', function () {
            $onResolve = Double::instance(['magicMethods' => true]);
            $onReject = Double::instance(['magicMethods' => true]);
            expect($onResolve)->toReceive('__invoke')->with("done");
            expect($onReject)->not->toReceive('__invoke');
            $f = function () {
                try {
                    yield reject("some reason");
                } catch (Exception $e) {
                    expect($e->getMessage())->toBe("some reason");
                }

                return "done";
            };
            asyncRun($f)->then($onResolve, $onReject);
        });

        it('should return throw on rejected promise', function () {
            $onResolve = Double::instance(['magicMethods' => true]);
            $onReject = Double::instance(['magicMethods' => true]);
            expect($onResolve)->not->toReceive('__invoke');
            expect($onReject)->toReceive('__invoke')->with(Arg::toBeAnInstanceOf(Exception::class));
            $f = function () {
                yield reject(new Exception("rejection reason"));
                return "done";
            };
            asyncRun($f)->then($onResolve, $onReject);
        });
    });
    describe('with loop', function () {
        given('loop', function () {
            return Factory::create();
        });
        given('wait', function () {
            return function ($time) {
                return new Promise(function ($resolve, $reject) use ($time) {
                    $this->loop->addTimer($time, function () use ($resolve, $reject, $time) {
                        if ($time < 0) {
                            return $reject("invalid");
                        }
                        return $resolve("waited {$time} seconds");
                    });
                });
            };
        });

        it('should work fine with loop based promises', function () {
            $this->time = microtime(true);
            $onResolve = function ($res) {
                $this->result = $res;
                $this->time = microtime(true) - $this->time;
                $this->loop->stop();
            };
            $onReject = function ($reason) {
            };
            $async = function ($init) {
                $wait = $this->wait;
                expect($init)->toBe("initial value");

                $res = yield $wait($n = 0.1);
                expect($res)->toBe("waited {$n} seconds");

                $res = yield all([$wait($n1 = 0.1), $wait($n2 = 0.2)]);
                expect($res)->toBe([
                    "waited {$n1} seconds",
                    "waited {$n2} seconds",
                ]);

                $res = yield race([$wait($n = 0.1), $wait(2)]);
                expect($res)->toBe("waited {$n} seconds");

                return "wow";
            };
            async($async("initial value"))->then($onResolve, $onReject);

            $this->loop->run();
            expect($this->result)->toBe('wow');
            expect($this->time)->toBeGreaterThan(0.4);
        });
    });
});