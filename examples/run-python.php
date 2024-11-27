<?php

use Takuya\ProcessExec\ExecArgStruct;
use Takuya\ProcOpen\ProcOpen;

require __DIR__.'/../vendor/autoload.php';

$proc = new ProcOpen(['python']);
$proc->setInput('
for i in range(5):
  print(i)
');
$proc->start();
//blocking io
echo stream_get_contents($proc->stdout());
