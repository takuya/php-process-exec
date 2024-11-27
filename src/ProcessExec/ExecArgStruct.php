<?php

namespace Takuya\ProcessExec;

use Takuya\ProcOpen\ProcOpen;
use InvalidArgumentException;

class ExecArgStruct {
  protected $cmd = [];
  protected $env;
  protected $cwd;
  protected $input;
  protected $stdout;
  protected $stderr;
  
  public function __construct ( ...$cmd ) {
    $this->setCmd( $cmd );
  }
  
  public function prepareProcess (): ProcOpen {
    $proc = new ProcOpen( ...$this->get_proc_args() );
    //$proc->setTimeout( null );
    //$proc->setIdleTimeout( null );
    //$proc->setTty( false );
    //$proc->setPty( false );
    $this->stdout && $proc->setStdout( $this->stdout );
    $this->stderr && $proc->setStderr( $this->stderr );
    return $proc;
  }
  
  protected function get_proc_args (): array {
    return [
      $this->getCmd(),
      $this->getCwd(),
      $this->getEnv(),
      $this->getInput(),
    ];
  }
  
  /**
   * @return array
   */
  public function getCmd (): array {
    return $this->cmd;
  }
  
  /**
   * @param array $cmd
   */
  public function setCmd ( array $cmd ): void {
    if ( sizeof( $cmd ) === 1 && is_string( $cmd[0] ) ) {
      if ( preg_match( '/(?<!(["\']))>+(?![^"\']*["\'])/', $cmd[0], $matches ) ) {
        throw new InvalidArgumentException( 'dont use ">". Please use `$this->setOutput("file");` instead.' );
      }
      //preg_match_all('/(?:[^\s"]+|"[^"]*")+/i', $cmd[0], $matches);
      preg_match_all( '/(\'[^\']*\'|"[^"]*"|\S+)/', $cmd[0], $matches );
      $cmd = $matches[0];
      foreach ( $cmd as $idx => $e ) {
        $cmd[$idx] = trim( $e, "'\"" );
      }
    }
    $flatten = [];
    array_walk_recursive( $cmd, function( $a ) use ( &$flatten ) { $flatten[] = $a; } );
    $this->cmd = $flatten;
  }
  
  /**
   * @return mixed
   */
  public function getCwd () {
    return $this->cwd;
  }
  
  /**
   * @param mixed $cwd
   */
  public function setCwd ( $cwd ): void {
    $this->cwd = $cwd;
  }
  
  /**
   * @return mixed
   */
  public function getEnv () {
    return $this->env;
  }
  
  /**
   * @param mixed $env
   */
  public function setEnv ( $env ): void {
    $this->env = $env;
  }
  
  /**
   * @return mixed
   */
  public function getInput () {
    return $this->input;
  }
  
  /**
   * @param mixed $input
   */
  public function setInput ( $input ): void {
    $this->input = $input;
  }
  
  public function setStdout ( $out ) {
    $this->stdout = $out;
  }
  
  public function setStderr ( $out ) {
    $this->stderr = $out;
  }
  
}