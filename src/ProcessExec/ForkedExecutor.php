<?php

namespace Takuya\ProcessExec;

use RuntimeException;
use Takuya\ProcessExec\Exceptions\ForkFailedException;
use Takuya\ProcessExec\Exceptions\ForkNotFoundException;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessCanceled;

class ForkedExecutor extends ProcessExecutor {
  
  protected $cpid;
  protected $shm;
  protected $gpid;
  
  public function getSubProcessId (): ?int {
    return $this->cpid;
  }
  public function getIsRunningFromPOSIX():bool{
    return posix_kill(intval($this->cpid), 0);
  }
  
  public function start () {
    if ( !function_exists( 'pcntl_fork' ) ) {
      throw new ForkNotFoundException();
    }
    $this->cpid = $this->fork();
    
    return $this->cpid;
  }
  
  protected function fork () {
    $pid = null;
    $cpid = null;
    $share_key = rand( 0, PHP_INT_MAX );
    $this->shm = $shm = shm_attach( $share_key );
    $pid = pcntl_fork();
    if ( $pid === -1 ) {
      throw new ForkFailedException( "pcntl_forkに失敗" );
    }
    if ( $pid === 0 ) {// 子プロセス
      //fclose(STDOUT);
      //fclose(STDERR);
      /*
       * メイン処理をexitすると、その後の処理ができなくなるので、ダブルフォークにする。
       * メイン側でexitが想定されてない場合への対応（laravelなど）
       */
      posix_setsid();
      $cpid = pcntl_fork();
      if ( $cpid === -1 ) {
        throw new ForkFailedException( "pcntl_forkに失敗:ダブルフォークに失敗" );
      }
      if ( $cpid > 0 ) {
        shm_put_var( $shm, $share_key, $cpid );
        @shm_detach( $shm );
        exit( 0 );
      } else {
        if ( $cpid === 0 ) { // 孫プロロセス
          @shm_detach( $shm );
          chdir( '/' );
          $this->setSignalHandlers();
          $this->handleSubProcess();
          exit( 0 );
        }
      }
    }
    pcntl_waitpid( $pid, $st );
    $cpid = shm_get_var( $shm, $share_key );
    shm_remove_var( $shm, $share_key );
    shm_remove( $shm );
    shm_detach( $shm );
    $this->gpid = $pid;
    $this->cpid = $cpid;
    
    return $cpid;
  }
  
  protected function setSignalHandlers () {
    $signal_handler = function( $sig ) {
      $pid = $this->getPid();
      try {
        $this->stop( 1, $sig === SIGQUIT ? SIGINT : $sig );
        pcntl_waitpid( $pid, $st );
        @shm_remove( $this->shm );
        @shm_detach( $this->shm );// if mac os
      } catch (RuntimeException $ex) {
        posix_kill( $pid, $sig === SIGQUIT ? SIGINT : $sig );
      } finally {
        $this->fireEvent( ProcessCanceled::class );
        $this->fireEvent( ProcessFinished::class );
        /**
         *   pid に正の値を指定した場合
         *      シグナル sig が pid で指定された ID を持つプロセスに送られる。
         *   pid  に 0 を指定した場合、
         *     呼び出し元のプロセスのプロセスグループに属するすべてのプロセスに sig で指定したシグナルが送られる。
         */
        posix_kill( 0, $sig );
        // ensure kill myself
        exit( 125 );
      }
    };
    pcntl_signal( SIGINT, $signal_handler );
    pcntl_signal( SIGTERM, $signal_handler );
    pcntl_signal( SIGHUP, $signal_handler );
    pcntl_signal( SIGQUIT, $signal_handler );
    pcntl_signal( SIGABRT, $signal_handler );
    $this->posix_ensure_signal_attach();
  }
}