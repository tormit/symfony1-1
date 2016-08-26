<?php

/**
 * Connection profiler.
 *
 * @package    sfDoctrinePlugin
 * @subpackage database
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfDoctrineConnectionProfiler extends Doctrine_Connection_Profiler
{
  protected
    $dispatcher = null,
    $options    = array();

  /**
   * Constructor.
   *
   * Available options:
   *
   *  * logging:              Whether to notify query logging events (defaults to false)
   *  * slow_query_threshold: How many seconds a query must take to be considered slow (defaults to 1)
   *
   * @param sfEventDispatcher $dispatcher
   * @param array             $options
   */
  public function __construct(sfEventDispatcher $dispatcher, $options = array())
  {
    $this->dispatcher = $dispatcher;
    $this->options = array_merge(array(
      'logging'              => false,
      'slow_query_threshold' => 1,
    ), $options);
  }

  /**
   * Returns an option value.
   *
   * @param  string $name
   *
   * @return mixed
   */
  public function getOption($name)
  {
    return isset($this->options[$name]) ? $this->options[$name] : null;
  }

  /**
   * Sets an option value.
   *
   * @param string $name
   * @param mixed  $value
   */
  public function setOption($name, $value)
  {
    $this->options[$name] = $value;
  }

  /**
   * Logs time and a connection query on behalf of the connection.
   *
   * @param Doctrine_Event $event
   */
  public function preQuery(Doctrine_Event $event)
  {
    if ($this->options['logging']) {
      $this->dispatcher->notify(
          new sfEvent(
              $event->getInvoker(), 'application.log',
              array(
                  sprintf(
                      "query : %s - (%s). \nQuery backtrace:\n%s",
                      $event->getQuery(),
                      join(', ', self::fixParams($event->getParams())),
                      $this->getBacktrace()
                  )
              )
          )
      );
    }

    sfTimerManager::getTimer('Database (Doctrine)');

    $args = func_get_args();
    $this->__call(__FUNCTION__, $args);
  }

  /**
   * Logs to the timer.
   *
   * @param Doctrine_Event $event
   */
  public function postQuery(Doctrine_Event $event)
  {
    sfTimerManager::getTimer('Database (Doctrine)', false)->addTime();

    $args = func_get_args();
    $this->__call(__FUNCTION__, $args);

    if ($event->getElapsedSecs() > $this->options['slow_query_threshold'])
    {
      $event->slowQuery = true;
    }
  }

  /**
   * Logs a connection exec on behalf of the connection.
   *
   * @param Doctrine_Event $event
   */
  public function preExec(Doctrine_Event $event)
  {
    if ($this->options['logging']) {
      $this->dispatcher->notify(
          new sfEvent(
              $event->getInvoker(), 'application.log',
              array(
                  sprintf(
                      "exec : %s - (%s). \nQuery backtrace:\n%s",
                      $event->getQuery(),
                      join(', ', self::fixParams($event->getParams())),
                      $this->getBacktrace()
                  )
              )
          )
      );
    }

    sfTimerManager::getTimer('Database (Doctrine)');

    $args = func_get_args();
    $this->__call(__FUNCTION__, $args);
  }

  /**
   * Logs to the timer.
   *
   * @param Doctrine_Event $event
   */
  public function postExec(Doctrine_Event $event)
  {
    sfTimerManager::getTimer('Database (Doctrine)', false)->addTime();

    $args = func_get_args();
    $this->__call(__FUNCTION__, $args);

    if ($event->getElapsedSecs() > $this->options['slow_query_threshold'])
    {
      $event->slowQuery = true;
    }
  }

  /**
   * Logs a statement execute on behalf of the statement.
   *
   * @param Doctrine_Event $event
   */
  public function preStmtExecute(Doctrine_Event $event)
  {
    if ($this->options['logging']) {
      $this->dispatcher->notify(
          new sfEvent(
              $event->getInvoker(), 'application.log',
              array(
                  sprintf(
                      "execute : %s - (%s). \nQuery backtrace:\n%s",
                      $event->getQuery(),
                      join(', ', self::fixParams($event->getParams())),
                      $this->getBacktrace()
                  )
              )
          )
      );
    }

    sfTimerManager::getTimer('Database (Doctrine)');

    $args = func_get_args();
    $this->__call(__FUNCTION__, $args);
  }

  /**
   * Logs to the timer.
   *
   * @param Doctrine_Event $event
   */
  public function postStmtExecute(Doctrine_Event $event)
  {
    sfTimerManager::getTimer('Database (Doctrine)', false)->addTime();

    $args = func_get_args();
    $this->__call(__FUNCTION__, $args);

    if ($event->getElapsedSecs() > $this->options['slow_query_threshold'])
    {
      $event->slowQuery = true;
    }
  }

  /**
   * Returns events having to do with query execution.
   *
   * @return array
   */
  public function getQueryExecutionEvents()
  {
    $events = array();
    foreach ($this as $event)
    {
      if (in_array($event->getCode(), array(Doctrine_Event::CONN_QUERY, Doctrine_Event::CONN_EXEC, Doctrine_Event::STMT_EXECUTE)))
      {
        $events[] = $event;
      }
    }

    return $events;
  }

  /**
   * Fixes query parameters for logging.
   *
   * @param  array $params
   *
   * @return array
   */
  static public function fixParams($params)
  {
    if (!is_array($params))
    {
      return array();
    }

    foreach ($params as $key => $param)
    {
      if (strlen($param) >= 255)
      {
        $params[$key] = '['.number_format(strlen($param) / 1024, 2).'Kb]';
      }
    }

    return $params;
  }

  /**
   * @return string
   */
  protected function getBacktrace()
  {
    $rootDir = sfConfig::get('sf_root_dir');

    if (isset($this->options['query_backtrace']) && $this->options['query_backtrace']) {
      $backtraceString = '';
      $appTrace = debug_backtrace();

      if (!$this->options['query_backtrace_full']) {
        $appTrace = array_filter(
            $appTrace,
            function ($row) {
              if (isset($row['file']) && stripos($row['file'], 'lib/vendor/symfony/lib') > 0) {
                return false;
              }
              return true;
            }
        );
      }

      foreach ($appTrace as $index => $row) {
        $source = '';
        if (isset($row['file'])) {
          $source = $row['file'] . '::' . $row['line'];
        } elseif (isset($row['class'])) {
          $source = $row['class'] . '::' . $row['function'];
        }

        $backtraceString .= sprintf("%s <- \n", str_replace($rootDir, '', $source));
      }

      return $backtraceString;
    } else {
      return 'DISABLED';
    }
  }
}
