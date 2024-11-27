<?php


use Takuya\ProcessExec\ProcessExecutor;
use Takuya\ProcessExec\ExecArgStruct;

require __DIR__.'/../vendor/autoload.php';

$arg = new ExecArgStruct();
$arg->setCmd( ['php'] );
$src =<<<'EOS'
<?php
foreach(range(0,4) as $i){
  printf("%d\n",$i);
}
EOS;
$arg->setInput( $src );
$executor = new ProcessExecutor( $arg );
$executor->onStdOut(function ($line){
  echo $line.PHP_EOL;
});
$executor->start();
