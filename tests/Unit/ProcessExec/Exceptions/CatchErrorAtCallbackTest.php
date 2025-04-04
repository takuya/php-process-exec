<?php

namespace Tests\Unit\ProcessExec\Exceptions;

use Tests\TestCase;
use RuntimeException;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\ProcessEvent;
use Takuya\ProcessExec\Exceptions\ProcessExitNonzero;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessReady;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStarted;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessSucceed;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessErrorOccurred;

class CatchErrorAtCallbackTest extends TestCase {
  
  public function test_catch_callback_occurs_php_error () {

    $this->expectException(\Error::class);
    $cmd = [
      '/usr/bin/bash','-c','echo hello;sleep 1;'
    ];
    $executor = new ProcessExecutor( new ExecArgStruct(...$cmd) );
    $executor->watch_interval = 0.01;
    $unknown = bin2hex(random_bytes(10));
    $callback = function($line) use($unknown,$executor){
      $unknown($line);
    };
    $executor->onStdout($callback);
    $executor->start();
  }
}