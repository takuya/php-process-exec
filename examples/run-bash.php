<?php


use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ExecArgStruct;

require __DIR__.'/../vendor/autoload.php';

$executor = new ProcessExecutor(new ExecArgStruct('bash'));
$executor->setInput('
for i in {0..4}; do
  echo $i
done;
');
$executor->start();
//blocking io
echo $executor->getOutput();
