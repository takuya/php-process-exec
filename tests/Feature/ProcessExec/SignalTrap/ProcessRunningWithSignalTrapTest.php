<?php

namespace Tests\Feature\ProcessExec\SignalTrap;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessCanceled;

class ProcessRunningWithSignalTrapTest extends TestCase {
  
  public function test_run_signal_trap_callback () {
    $signaled = 0;
    $p1 = new ProcessExecutor( new ExecArgStruct( 'php -r "usleep(1000*1);"' ) );
    $p1->watch_interval = 0.01;
    $obs = new ProcessObserver();
    $obs->addEventListener( ProcessRunning::class, function( ProcessRunning $ev ) {
      $ev->getExecutor()->signal( SIGTERM );
    } );
    $obs->addEventListener( ProcessCanceled::class, function( ProcessCanceled $ev ) use ( &$signaled ) {
      $signaled++;
    } );
    $p1->addObserver( $obs );
    $p1->start();
    $this->assertEquals( 'signaled', $p1->getStatus() );
    $this->assertEquals( 1, $signaled );// assert called once.
  }
}