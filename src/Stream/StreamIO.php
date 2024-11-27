<?php

namespace Takuya\Stream;

use Takuya\ProcOpen\Exceptions\InvalidStreamException;

class StreamIO {
  
  protected $st;
  
  public function __construct ( $res ) {
    $this->setStream( $res );
  }
  
  protected function setStream ( $res ) {
    if ( !is_resource( $res ) ) {
      throw new InvalidStreamException( 'not a stream.' );
    }
    $this->st = $res;
  }
  
  public function getReader () {
    return new StreamReader( $this );
  }
  
  public function readReady ( $timeout_sec = 0, $timeout_micro = 10 ) {
    if ( $r = $this->select_read( $timeout_sec = 0, $timeout_micro = 10 ) > 0 ) {
      return true;
    }
    
    return false;
  }
  
  protected function select_read ( $timeout_sec = 0, $timeout_micro = 10 ) {
    $r = [$this->st];
    
    // block until ready or timeout.
    return $this->select( $r, $w, $e, $timeout_sec, $timeout_micro );
  }
  
  public function select ( &$r, &$w, &$ex, $timeout_sec = 0, $timeout_micro = 10 ) {
    return $this->isMemoryWrapper() || stream_select( $r, $w, $e, $timeout_sec, $timeout_micro );
  }
  
  public function isMemoryWrapper () {
    $meta = stream_get_meta_data( $this->st );
    
    return !empty( $meta["stream_type"] ) && 'MEMORY' == $meta["stream_type"];
  }
  
  public function writeReady ( $timeout_sec = 0, $timeout_micro = 10 ) {
    if ( $this->select_write( $timeout_sec = 0, $timeout_micro = 10 ) > 0 ) {
      return true;
    }
    
    return false;
  }
  
  protected function select_write ( $timeout_sec = 0, $timeout_micro = 10 ) {
    $w = [$this->st];
    
    // block until ready or timeout.
    return $this->select( $r, $w, $e, $timeout_sec, $timeout_micro );
  }
  
  public function getStream () {
    return $this->st;
  }
}