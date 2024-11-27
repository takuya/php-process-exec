<?php

namespace Tests\Unit\ProcessExec\EventHandler;

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

class ProcessExecutorEventHandlerTest extends TestCase {
  
  public function test_process_exec_running_event_observed () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
        for($i=0;$i<5;$i++){
          echo 'Hello World'.PHP_EOL;
          usleep(1000*120);
        }
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $observer = new ProcessObserver();
    $executor->watch_interval = 0.001;
    $running_called_cnt = 0;
    $observer->addEventListener( event: ProcessRunning::class, listener: function( ProcessEvent $event ) use (
      &
      $running_called_cnt
    ) {
      $running_called_cnt++;
    } );
    $executor->addObserver( $observer );
    $executor->start();
    $this->assertGreaterThanOrEqual( 4, $running_called_cnt );
  }
  
  public function test_process_event_failed_exit_non_zero_will_raise_exception () {
    $this->expectException( ProcessExitNonzero::class );
    $p1 = new ProcessExecutor( new ExecArgStruct( 'php -r "syntax_error()"' ) );
    $p1->throw_exception = true;
    $p1->start();
  }
  
  public function test_process_event_failed_ignore_exception () {
    $p1 = new ProcessExecutor( new ExecArgStruct( 'php -r "syntax_error()"' ) );
    $p1->throw_exception = false;
    $p1->start();
    $this->assertEquals( 255, $p1->getExitCode() );
    $this->assertEquals( 'terminated', $p1->getStatus() );
    $this->assertStringContainsString( 'PHP Parse error:', $p1->getErrout() );
    $this->assertFalse( $p1->isSuccessful() );
    $this->assertEquals( ProcessExitNonzero::class, get_class( $p1->getLastException() ) );
  }
  
  public function test_process_exec_error_observed () {
    $id = uniqid( __METHOD__ );
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
        for($i=0;$i<10;$i++){
          echo 'Hello World'.PHP_EOL;
          usleep(1000*10);
        }
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 0.01;
    $executor->throw_exception = false;
    $observer = new ProcessObserver();
    $is_error_called = false;
    $observer->addEventListener( ProcessErrorOccurred::class,
      function( ProcessEvent $event ) use ( &$is_error_called, $id ) {
        $ex = $event->getExecutor()->getLastException();
        $this->assertEquals( $id, $ex->getMessage() );
        $event->getExecutor()->signal( SIGHUP );
        $this->assertEquals( 'signaled', $event->getExecutor()->getStatus() );
        $is_error_called = true;
      } );
    $observer->addEventListener( ProcessRunning::class, function() use ( $id ) {
      throw new RuntimeException( $id );
    } );
    $executor->addObserver( $observer );
    $executor->start();
    $this->assertTrue( $is_error_called );
  }
  
  public function test_process_exec_with_observe_ready () {
    /** @var ExecArgStruct $arg */ /** @var ProcessExecutor $executor */
    /** @var ProcessObserver $observer */
    [$arg, $executor, $observer] = $this->prepare();
    $src = $arg->getInput();
    $is_ready_called = false;
    $observer->addEventListener( ProcessReady::class, function( ProcessEvent $event ) use ( &$is_ready_called, $src ) {
      $is_ready_called = true;
      $this->assertEquals( $src, $event->getExecutor()->getInput() );
    } );
    $executor->start();
    $this->assertTrue( $is_ready_called );
  }
  
  public function prepare () {
    $arg = new ExecArgStruct();
    $arg->setCmd( ['php'] );
    $src = <<<'EOS'
      <?php
        for($i=0;$i<10;$i++){
          echo 'Hello World'.PHP_EOL;
          usleep(1000*10);
        }
      EOS;
    $arg->setInput( $src );
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 0.001;
    $observer = new ProcessObserver();
    $executor->addObserver( $observer );
    
    return [$arg, $executor, $observer];
  }
  
  public function test_process_exec_with_observe_started () {
    /** @var ExecArgStruct $arg */ /** @var ProcessExecutor $executor */
    /** @var ProcessObserver $observer */
    [$arg, $executor, $observer] = $this->prepare();
    $is_start_called = false;
    $observer->addEventListener( ProcessStarted::class, function( ProcessEvent $event ) use ( &$is_start_called ) {
      $is_start_called = true;
      $this->assertEquals( 'started', $event->getExecutor()->getStatus() );
      $this->assertNull( $event->getExecutor()->getExitCode() );
      $this->assertIsInt( $event->getExecutor()->getPid() );
      $this->assertEmpty( "", $event->getExecutor()->getOutput() );
    } );
    $executor->start();
    $this->assertTrue( $is_start_called );
  }
  
  public function test_process_exec_with_observe_running () {
    /** @var ExecArgStruct $arg */ /** @var ProcessExecutor $executor */
    /** @var ProcessObserver $observer */
    [$arg, $executor, $observer] = $this->prepare();
    $is_running_called = false;
    $observer->addEventListener( ProcessRunning::class, function( ProcessEvent $event ) use ( &$is_running_called ) {
      $is_running_called = true;
    } );
    $executor->start();
    $this->assertTrue( $is_running_called );
  }
  
  public function test_process_exec_with_observe_success () {
    /** @var ExecArgStruct $arg */ /** @var ProcessExecutor $executor */
    /** @var ProcessObserver $observer */
    [$arg, $executor, $observer] = $this->prepare();
    $is_success_called = false;
    $observer->addEventListener( ProcessSucceed::class, function( ProcessEvent $event ) use ( &$is_success_called ) {
      $is_success_called = true;
      $this->assertTrue( $event->getExecutor()->isSuccessful() );
      $this->assertEquals( 10, substr_count( $event->getExecutor()->getOutput(), 'Hello World' ) );
    } );
    $executor->start();
    $this->assertTrue( $is_success_called );
  }
  
  public function test_process_exec_with_observe_finish () {
    /** @var ExecArgStruct $arg */ /** @var ProcessExecutor $executor */
    /** @var ProcessObserver $observer */
    [$arg, $executor, $observer] = $this->prepare();
    $is_finish_called = false;
    $observer->addEventListener( ProcessFinished::class, function( ProcessEvent $event ) use ( &$is_finish_called ) {
      $out = $event->getExecutor()->getOutput();
      $is_finish_called = true;
      $this->assertEquals( 10, substr_count( $out, 'Hello World' ) );
    } );
    //
    $executor->start();
    $this->assertTrue( $is_finish_called );
  }
  
  public function test_process_exec_with_observer_all () {
    /** @var ExecArgStruct $arg */ /** @var ProcessExecutor $executor */
    /** @var ProcessObserver $observer */
    [$arg, $executor, $observer] = $this->prepare();
    $src = $arg->getInput();
    $executor = new ProcessExecutor( $arg );
    $executor->watch_interval = 0.001;
    $observer = new ProcessObserver();
    $is_ready_called = false;
    $observer->addEventListener( ProcessReady::class, function( ProcessEvent $event ) use ( &$is_ready_called, $src ) {
      $is_ready_called = true;
      $this->assertEquals( $src, $event->getExecutor()->getInput() );
    } );
    $is_start_called = false;
    $observer->addEventListener( ProcessStarted::class, function( ProcessEvent $event ) use ( &$is_start_called ) {
      $is_start_called = true;
      $this->assertEquals( 'started', $event->getExecutor()->getStatus() );
      $this->assertIsInt( $event->getExecutor()->getPid() );
      $this->assertNull( $event->getExecutor()->getExitCode() );
      $this->assertEmpty( "", $event->getExecutor()->getOutput() );
    } );
    $is_running_called = false;
    $observer->addEventListener( ProcessRunning::class, function( ProcessEvent $event ) use ( &$is_running_called ) {
      $is_running_called = true;
      //$this->assertEquals( 'started', $event->getExecutor()->getStatus() );
      //$this->assertNull( $event->getExecutor()->getExitCode() );
      //$this->assertIsInt( $event->getExecutor()->getPid() );
    } );
    $is_success_called = false;
    $observer->addEventListener( ProcessSucceed::class, function( ProcessEvent $event ) use ( &$is_success_called ) {
      $is_success_called = true;
      $this->assertTrue( $event->getExecutor()->isSuccessful() );
      $this->assertEquals( 10, substr_count( $event->getExecutor()->getOutput(), 'Hello World' ) );
    } );
    $is_finish_called = false;
    $observer->addEventListener( ProcessFinished::class, function( ProcessEvent $event ) use ( &$is_finish_called ) {
      $out = $event->getExecutor()->getOutput();
      $is_finish_called = true;
      $this->assertEquals( 10, substr_count( $out, 'Hello World' ) );
    } );
    //
    $executor->addObserver( $observer );
    $executor->start();
    //
    $this->assertTrue( $is_ready_called );
    $this->assertTrue( $is_start_called );
    $this->assertTrue( $is_running_called );
    $this->assertTrue( $is_success_called );
    $this->assertTrue( $is_finish_called );
  }
}