<?php

namespace ProcessExec\Signals;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\ProcessEvent;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStopped;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessResumed;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessStarted;

class SuspendResumeTest extends TestCase {
  public function test_process_suspend_resume_and_fires_event () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php sleep(3000);
    EOS;
    $arg->setInput( $src );
    $p1 = new ProcessExecutor( $arg );
    $p1->watch_interval = 0.01;
    
    $obs = new class extends ProcessObserver {
      
      public int $resume_called = 0;
      public int $suspend_called = 0;
      public int $limit = 4;
      
      public function __construct () {
        parent::__construct();
        $this->addEventListener( ProcessStarted::class, function( ProcessEvent $ev ) {
          $ev->getExecutor()->suspend();
        } );
        $this->addEventListener( ProcessStopped::class, function( ProcessEvent $ev ) {
          $this->suspend_called++;
          $ev->getExecutor()->resume();
        } );
        $this->addEventListener( ProcessResumed::class, function( ProcessEvent $ev ) {
          if ( $this->limit < ++$this->resume_called ) {
            $ev->getExecutor()->signal( SIGTERM );
            return null;
          }
          $ev->getExecutor()->suspend();
        } );
      }
    };
    
    
    //
    $p1->addObserver( $obs );
    $p1->start();
    //
    $this->assertGreaterThanOrEqual( $obs->limit + 1, $obs->resume_called );
    $this->assertGreaterThanOrEqual( $obs->limit + 1, $obs->suspend_called );
  }
}