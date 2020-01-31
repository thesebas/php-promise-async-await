<?php

namespace thesebas\promise;

use Exception;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Throwable;

/**
 * @param callable $callable
 * @param mixed    ...$init
 *
 * @return Promise
 */
function asyncRun($callable, ...$init)
{
    return async($callable(...$init));
}

/**
 * @param \Generator $gen
 *
 * @return Promise
 */
function async($gen)
{
    return new Promise(function ($resolve, $reject) use ($gen) {
        /** @var PromiseInterface $promise */
        $promise = $gen->current();

        $r = function ($res) use ($gen, &$r, &$f, $resolve, $reject) {
            $gen->send($res);
            if (!$gen->valid()) {
                $ret = $gen->getReturn();
                $resolve($ret);
            }
            $newProm = $gen->current();

            return $newProm->then($r, $f);
        };
        $f = function ($reason) use ($gen, &$r, &$f, $resolve, $reject) {
            try {
                $toThrow = $reason instanceof Throwable ? $reason : new Exception($reason);
                $gen->throw($toThrow);

                if (!$gen->valid()) {
                    return $resolve($gen->getReturn());
                }

                $newProm = $gen->current();
                return $newProm->then($r, $f);
            } catch (Exception $e) {
                return $reject($e);
            }
        };

        $promise->then($r, $f);
    });
}