<?php

namespace Tests\Feature\ProcessExec\SignalTrap;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStopped;

class ProcessRunningWithSignalTest extends TestCase {
  
  public function test_detect_suspend_signal_dispatched () {
    $on_running_called = 0;
    $on_suspend_called = 0;
    $p1 = new ProcessExecutor( new ExecArgStruct( 'php -r "usleep(1000*2);"' ) );
    $p1->watch_interval = 0.01;
    $obs = new ProcessObserver();
    $obs->addEventListener( ProcessRunning::class, function( ProcessRunning $ev ) use ( &$on_running_called ) {
      $ev->getExecutor()->getProcess()->suspend();
      $on_running_called++;
    } );
    $obs->addEventListener( ProcessStopped::class, function( ProcessStopped $ev ) use ( &$on_suspend_called ) {
      $ev->getExecutor()->getProcess()->resume();
      $on_suspend_called++;
    } );
    $p1->addObserver( $obs );
    $p1->start();
    $this->assertGreaterThan( 1, $on_running_called );
    $this->assertGreaterThan( 1, $on_suspend_called );
  }
}