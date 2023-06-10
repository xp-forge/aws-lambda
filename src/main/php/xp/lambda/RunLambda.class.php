<?php namespace xp\lambda;

use com\amazon\aws\lambda\{Environment, Context};
use lang\{XPClass, Throwable};
use util\UUID;
use util\cmd\Console;

/**
 * Run lambdas locally
 *
 * @see https://docs.aws.amazon.com/de_de/lambda/latest/dg/runtimes-api.html
 */
class RunLambda {
  const TRACE= 'Root=1-5bef4de7-ad49b0e87f6ef6c87fc2e700;Parent=9a9197af755a6419;Sampled=1';
  const REGION= 'test-local-1';

  private $impl, $events;

  /**
   * Creates a new `run` subcommand
   *
   * @param  string $handler
   * @param  string... $events
   * @throws lang.ClassLoadingException
   */
  public function __construct($handler= 'Handler', ... $events) {
    $this->impl= XPClass::forName($handler);
    $this->events= $events ?: ['{}'];
  }

  /** Runs this command */
  public function run(): int {
    $name= $this->impl->getSimpleName();
    $region= getenv('AWS_REGION') ?: self::REGION;
    $functionArn= "arn:aws:lambda:{$region}:123456789012:function:{$name}";
    $deadlineMs= (time() + 900) * 1000;
    $environment= $_ENV + ['AWS_LAMBDA_FUNCTION_NAME' => $name, 'AWS_REGION' => $region];

    try {
      $target= $this->impl->newInstance(new Environment(getcwd(), Console::$out, []))->target();
      $lambda= $target instanceof Lambda ? [$target, 'process'] : $target;
    } catch (Throwable $e) {
      Console::$err->writeLine($e);
      return 1;
    }

    $status= 0;
    foreach ($this->events as $event) {
      $headers= [
        'Lambda-Runtime-Aws-Request-Id'       => [UUID::randomUUID()->hashCode()],
        'Lambda-Runtime-Invoked-Function-Arn' => [$functionArn],
        'Lambda-Runtime-Trace-Id'             => [self::TRACE],
        'Lambda-Runtime-Deadline-Ms'          => [$deadlineMs],
        'Content-Length'                      => [strlen($event)],
      ];

      try {
        $result= $lambda(json_decode($event, true), new Context($headers, $environment));
        Console::$out->writeLine($result);
      } catch (Throwable $e) {
        Console::$err->writeLine($e);
        $status= 1;
      }
    }
    return $status;
  }
}