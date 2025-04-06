<?php

namespace Takuya\ProcessExec\Traits;

use Takuya\ProcOpen\ProcOpen;
use Takuya\ProcessExec\ProcessEvents\ProcessEvent;
use Takuya\ProcessExec\ProcessEvents\Events\StdoutChanged;
use Takuya\ProcessExec\ProcessEvents\Events\StderrChanged;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessFinished;

trait ObserveProcessStream {
  
  
  public function onStdOut ( $callback, $delim = "\n" ) {
    $this->onStreamLine( ProcOpen::STDOUT, StdoutChanged::class, $callback, $delim );
  }
  
  protected function onStreamLine ( $fd_no, $class, $callback, $delim ) {
    // read at all possible.
    $this->addEventListener( $class, function( ProcessEvent $ev ) use ( $fd_no, $delim, $callback ) {
      while ( ($line = $ev->getExecutor()->getStream( $fd_no )->getReader()->readLine( $delim ))!==false ) {
        $callback( $line );
      }
    } );
    // read remains on process finished.
    $this->addEventListener(
      ProcessFinished::class,
      fn( ProcessEvent $ev ) => array_map( $callback, iterator_to_array(
        $ev->getExecutor()->getStream( $fd_no )->getReader()->readAll( $delim )
      ) ) );
  }
  
  public function onStdErr ( $callback, $delim = "\n" ) {
    $this->onStreamLine( ProcOpen::STDERR, StderrChanged::class, $callback, $delim );
  }
}