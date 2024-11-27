<?php

namespace Tests\Unit\ProcessExec;

use Exception;
use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ForkedExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\ProcessEvent;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;

class ForkedExecutorTest extends TestCase {
  
  public function test_process_forked_execute () {
    $share_key = rand( 0, PHP_INT_MAX );
    $shm = shm_attach( $share_key );
    try {
      $arg = new ExecArgStruct();
      $arg->setCmd( ['php'] );
      $src = <<<"EOS"
      <?php print('Hello World($share_key)');usleep(2);
      EOS;
      $arg->setInput( $src );
      $executor = new ForkedExecutor( $arg );
      $observer = new ProcessObserver();
      $executor->addObserver( $observer );
      $observer->addEventListener( ProcessFinished::class,
        fn( ProcessEvent $ev ) => shm_put_var( $shm, $share_key, $ev->getExecutor()->getOutput() ) );
      $executor->start();
      $pid = $executor->getSubProcessId();
      while ( posix_kill( $pid, 0 ) ) {
        usleep( 1 );
      }
      $out = shm_get_var( $shm, $share_key );
      $this->assertEquals( "Hello World($share_key)", $out );
    } catch (Exception $e) {
    } finally {
      // なぜか、macではペアで呼ばないとだめ
      shm_remove( $shm );
      @shm_detach( $shm ); // if mac os
    }
  }
}