<?php

namespace Takuya\ProcessExec\Traits;

use Takuya\ProcessExec\Exceptions\TimeoutException;
use Takuya\ProcessExec\Exceptions\HasNotStartedException;
use Takuya\ProcessExec\Enum\ProcessExitCode;

trait ProcessCompatSymfony {
  // utils for Symfony::Process alternatives
  /**
   * @var resource
   */
  protected $stdout_buffer;
  /**
   * @var resource
   */
  protected $stderr_buffer;
  /**
   * @var resource
   */
  protected $input_buffer;
  
  public function getPid () {
    return $this->proc->info->pid;
  }
  
  public function getStatus () {
    if ( empty( ( $this->proc->info ) ) ) {
      return 'ready';
    }
    if ( $this->proc->info->running ) {
      return 'started';
    } else {
      if ( $this->proc->info->termsig ) {
        return 'signaled';
      }
      return 'terminated';
    }
  }
  
  public function getInput () {
    return $this->struct->getInput();
  }
  
  public function isSuccessful () {
    return 0 === $this->getExitCode();
  }
  
  public function getExitCode () {
    $c = $this->proc->info->exitcode;
    return $c == -1 ? null : $c;
  }
  
  public function getOutput () {
    return $this->buffred_io( 'stdout' );
  }
  
  protected function buffred_io ( $name ) {
    $buff_name = "{$name}_buffer";
    if ( empty( $this->proc->info ) ) {
      throw new HasNotStartedException();
    }
    if ( $this->proc->info->running ) {
      return '';
    }
    if ( empty( $this->$buff_name ) ) {
      $this->$buff_name = fopen( 'php://memory', 'w+' );
      $seekable = stream_get_meta_data($this->proc->$name())['seekable'];
      $seekable  && rewind($this->proc->$name());
      stream_copy_to_stream( $this->proc->$name(), $this->$buff_name );
    }
    rewind( $this->$buff_name );
    return stream_get_contents( $this->$buff_name );
  }
  
  public function getErrout () {
    return $this->buffred_io( 'stderr' );
  }
  
  public function stop ( $timeout = 3, $sig = SIGHUP ) {
    $this->proc->signal( $sig );
    $called_at = microtime( true );
    while ( $this->proc->info->running ) {
      usleep( 1000 );
      $duration = microtime( true ) - $called_at;
      if ( $duration > $timeout ) {
        throw new TimeoutException( 'Timeout' );
      }
    }
  }
  
  public function getExitCodeText () {
    return ProcessExitCode::getExitCodeText( $this->getExitCode() );
  }
}