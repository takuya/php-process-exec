<?php

namespace Takuya\ProcessExec\Traits;

use Takuya\Stream\StreamIO;
use Takuya\ProcessExec\Observers\ProcessStreamObserver;

trait ProcessStreaming {
  public function onStdout ( $callback, $delim = "\n" ) {
    $obs = new ProcessStreamObserver();
    $obs->onStdOut( $callback, $delim );
    $this->addObserver( $obs );
  }
  
  public function onStderr ( $callback, $delim = "\n" ) {
    $obs = new ProcessStreamObserver();
    $obs->onStdErr( $callback, $delim );
    $this->addObserver( $obs );
  }
  
  protected function fireStreamEvent ( $fd, $class, $seconds = 0, $micro_sec = 10 ) {
    if ( $this->getStream( $fd )->readReady( $seconds, $micro_sec ) ) {
      $this->fireEvent( $class );
    }
  }
  
  public function getStream ( $fd ): StreamIO {
    return new StreamIO( $this->getProcess()->getFd( $fd ) );
  }
}