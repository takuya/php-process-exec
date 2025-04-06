<?php

namespace Takuya\ProcessExec\Traits;
trait ProcessExecutorWaitHandler {
  
  
  /** @var float time. default is 10.000000 sec. minimum is 1000micro sec = 0.001 sec */
  public $watch_interval = 10.000;
  
  // TODO この機能はObserver側で持つべきかもしれない。
  protected function watcher ( callable $callback ): callable {
    $last_called_at = null;
    return function() use ( &$last_called_at, $callback ) {
      if ( is_null( $last_called_at ) ) {// first time
        call_user_func( $callback );
        $last_called_at = microtime( true );
        return;
      }
      //main
      $duration = microtime( true ) - $last_called_at;
      if ( $duration > $this->watch_interval ) {
        call_user_func( $callback );
        $last_called_at = microtime( true );
      }
    };
  }
  
}