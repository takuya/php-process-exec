<?php

namespace Tests\Unit\ProcessExec\Stream;

use Tests\TestCase;
use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ProcessObserver;
use Takuya\ProcessExec\ProcessEvents\ProcessEvent;
use Takuya\ProcessExec\ProcessEvents\Events\StdoutChanged;
use Takuya\ProcessExec\ProcessEvents\Events\StderrChanged;

class ProcessRedirectTest extends TestCase {
  
  public function test_getOutput_getErrout_can_read() {
    $arg = new ExecArgStruct();
    $arg->setCmd(['php']);
    $src = <<<'EOS'
      <?php
      file_put_contents("php://stdout",str_repeat("Hello World\n",10));
      file_put_contents("php://stderr",str_repeat("Hello Error\n",10));
      EOS;
    $arg->setInput($src);
    $arg->setStdout(fopen('php://temp','w'));
    $arg->setStderr(fopen('php://temp','w'));
    $executor = new ProcessExecutor($arg);
    $executor->start();
    $this->assertEquals(10,substr_count($executor->getOutput(), 'Hello World'));
    $this->assertEquals(10,substr_count($executor->getErrout(), 'Hello Error'));
    // auto rewind.
    $this->assertEquals(10,substr_count($executor->getOutput(), 'Hello World'));
    $this->assertEquals(10,substr_count($executor->getErrout(), 'Hello Error'));
    
  }
}
