<?php


use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ExecArgStruct;

require __DIR__.'/../vendor/autoload.php';

$p1 = new ProcessExecutor(new ExecArgStruct('bash'));
$p1->setInput('
for i in {0..4}; do
  echo $i
done;
');
$p2 = $p1->pipe(new ExecArgStruct('grep -P "\d" '));
$p2->start();
echo $p2->getOutput();

