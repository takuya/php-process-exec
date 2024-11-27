<?php

namespace Takuya\ProcessExec\Traits;

use Takuya\ProcOpen\ProcOpen;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\Events\ProcessRunning;

trait ProgressiveInput {
  
  /**
   * @var callable $on_progress
   */
  protected $on_progress_input = null;
  
  /**
   * @param callable(string $percent) $on_progress_input
   * @param int $write_byte_size
   * @return void
   */
  public function onInputProgress ( callable $on_progress_input, int $write_byte_size = 1024 ) {
    // input を null にして、callback で writeする。
    $input = $this->getInput();
    $this->setInput( null );
    $this->watch_interval = 0;
    $total = fstat( $input )['size'];
    //
    $this->on_progress_input = ( function() use ( $on_progress_input, $write_byte_size, $total, $input ) {
      // utils of callbacks
      $is_closed = fn( $stream ) => false == is_resource( $stream );
      $input_eof = fn() => feof( $input );
      $select = function( $to_stream ) {
        [$r, $w, $e] = [[], [$to_stream], []];
        $ret = stream_select( $r, $w, $e, 0, 100 );
        
        return $ret == 1;
      };
      $copy_input = fn( $to_stream ) => stream_copy_to_stream( $input, $to_stream, $write_byte_size );
      $fire_event = fn( $proc ) => $on_progress_input( ftell( $input )/$total*100 );
      
      // build on_progress ;
      return function( ProcessRunning $ev ) use ( $is_closed, $input_eof, $select, $copy_input, $fire_event, $input ) {
        $proc = $ev->getExecutor();
        if ( $is_closed( $proc->getProcess()->getFd( ProcOpen::STDIN ) ) ) {
          return;
        }
        if ( $input_eof() ) {
          fclose( $proc->getProcess()->getFd( ProcOpen::STDIN ) );
          
          return;
        }
        if ( $select( $proc->getProcess()->getFd( ProcOpen::STDIN ) ) ) {
          $copy_input( $proc->getProcess()->getFd( ProcOpen::STDIN ) );
          $fire_event( $proc );
        }
      };
    } )();
  }
  
  protected function has_input_progreess_monitor () {
    return !empty( $this->on_progress_input );
  }
  
  protected function monitorInputProgress () {
    $obs = new ProcessObserver();
    $obs->addEventListener( ProcessRunning::class, $this->on_progress_input );
    $this->addObserver( $obs );
  }
}