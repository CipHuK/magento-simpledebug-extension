<?php

class CipHuK_Debug_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_ENABLED   = 'debug/debug/enabled';
    const XML_PATH_LOG   = 'debug/debug/logenabled';

    protected $_logger;

    public function isEnabled()
    {
        return Mage::getStoreConfig( self::XML_PATH_ENABLED );
    }

    public function isLogEnabled()
    {
        return Mage::getStoreConfig( self::XML_PATH_LOG );
    }

    protected function log($string)
    {
        if (is_null($this->_logger))
        {
            $file = 'debug.log';
            $logDir  = Mage::getBaseDir('var') . DS . 'log';
            $logFile = $logDir . DS . $file;

            if (!is_dir($logDir)) {
                mkdir($logDir);
                chmod($logDir, 0777);
            }

            if (!file_exists($logFile)) {
                file_put_contents($logFile, '');
                chmod($logFile, 0777);
            }

            $this->_logger = new Zend_Log();

            $writer = new Zend_Log_Writer_Stream($logFile);
            $this->_logger->addWriter($writer);

            $filter = new Zend_Log_Filter_Priority(Zend_Log::INFO);
            $this->_logger->addFilter($filter);
        }
        $this->_logger->log(var_export($string,1), Zend_Log::INFO);
    }

    private static function wrapOutput($funcName, $content, $colorCode, $borderColor)
    {
        $backtrace = debug_backtrace();
        array_shift($backtrace);

        $log = '<pre style="white-space:pre-wrap; border-radius: 8px; border: 3px double ' . $borderColor . '; background-color: ' . $colorCode . '; padding: 3px; margin: 2px 0 0 0; color: #000; ">';

        if ( $backtrace[1]['function'] == 'call_user_func_array' ) {
            $headBacktrace = $backtrace[2];
        } else {
            $headBacktrace = $backtrace[0];
        }

        $relativePath = str_replace(getcwd(), '.', $headBacktrace['file']);

        $log .= '<div style="padding: 0 0 4px">'
        . '<span style="color: #00d">Function:</span> '
        . '<span style="color: #000">' . $funcName . '</span> '
        . '<span style="color: #00d">File:</span> '
        . '<span style="color: #000">' . $relativePath . '</span> '
        . '<span style="color: #00d">Line:</span> '
        . '<span style="color: #f00">' . $headBacktrace['line'] . '</span>'
        . '</div>';

        $log .= $content . '</pre>';
        return $log;
    }

    public function backtrace($level = null)
    {
        $backtrace = debug_backtrace();

        $shiftCount = ($backtrace[1]['function'] == 'call_user_func_array')
                    ? 3
                    : 1;

        for ($i = 0; $i < $shiftCount; ++$i) { array_shift($backtrace); }

        $log = '';
        $i = 0;
        foreach ($backtrace as $var) {
            if (!empty($level) && ((int)$level <= $i)) { break; }
            ++$i;
            $log .= '<div style="border: 1px solid #090; margin: 3px 0 0 0; background-color: #efe;">'
                . '<span style="border-bottom: 1px dashed #090; background-color: #fff;">';

            if (isset($var['file']) ) {
                $log .= '<span style="color: #00d">File:</span> '
                    . '<span style="color: #000">' . str_replace(getcwd(), '.', $var['file']) . '</span> '
                    . '<span style="color: #00d">Line:</span> '
                    . '<span style="color: #f00">' . $var['line'] . '</span>';
            } else {
                $log .= '<span style="color: #909">Function has been called without file</span>';
            }
            $log .= "</span>\n";

            if (!empty($var['class'])) {
                $log .=  '<span>' . $var['class'] . '</span>'
                    . '<span>' . $var['type']  . '</span>';
            }
            $arrArgs = array();
            foreach ($var['args'] as $argument) {
                if (is_object($argument))
                    $arrArgs[] =  get_class($argument);
                elseif (is_array($argument))
                    $arrArgs[] = gettype($argument) . '<em>{' . count($argument) . '}</em>';
                else
                    $arrArgs[] = '\'' . $argument . '\'';
            }
            $log .= '<span>' . $var['function']
                . '(' . implode(', ', $arrArgs) . ')'
                . '</span>'
                . "\n"
                . '</div>';
        }

        $log = self::wrapOutput('Backtrace(' . (int)$level . ')', $log, '#dfd', '#090');

        if (self::isLogEnabled()) $this->log(strip_tags($log) . "\n");
        if (self::isEnabled()) echo $log;
    }

    public function dump()
    {
        $args = func_get_args();

        $log = '';
        foreach ($args as $argument) {
            $log .= '<div style="border: 1px solid #900; margin:2px 0 0 0; background: #fee">';
            if (is_array($argument)) {
                if (count($argument) < 80) {
                    $log .= "Array(\n";
                    foreach ($argument as $key => $var) {
                        if (is_array($var)) {
                            $log .= "{$key} => Array<em>{" . count($var) . "}</em>, \n";
                        } elseif (is_object($var)) {
                            $log .= "{$key} => " . get_class($var) . ", \n";
                        } else {
                            $log .= "{$key} => " . var_export($var,1) . ", \n";
                        }
                    }
                    $log .= ')';
                } else {
                    $log .= 'Array<em>{' . count($argument) .  '}</em>';
                }
            }elseif (is_object($argument)) {
                $log .= get_class($argument);
            } elseif (is_string($argument)) {
                $log .= "'{$argument}'";
            } else {
                $log .= var_export($argument,1);
            }
            $log .= '</div>';
        }

        $log = self::wrapOutput('Dump()', $log, '#fdd', '#900');
        if (self::isLogEnabled()) $this->log(strip_tags($log) . "\n");
        if (self::isEnabled()) echo $log;
    }

    public function timer($timer = 'default')
    {
        static $timersList = array();

        $log = '<div style="border: 1px solid #fc0; background-color: #fff; padding: 2px;">';
        $currMicrotime = microtime(1);
        if (!isset($timersList[$timer])) {
            $timersList[$timer] = $currMicrotime;
            $log .= 'Timer: "' . $timer . '" start';
        } else {
            $timersList[$timer] = $currMicrotime - $timersList[$timer];
            $log .= 'Timer: "' . $timer . '" stop ' . sprintf("%.6f",$timersList[$timer]);
        }
        $log .= '</div>';

        $log = self::wrapOutput('Timer(' . $timer . ')', $log, '#ffd', '#fc0');;
        if (self::isLogEnabled()) $this->log(strip_tags($log) . "\n");
        if (self::isEnabled()) echo $log;
    }

    public function stop()
    {
        $log = self::wrapOutput('Stop()', '', '#eee', '#333');;
        if (self::isEnabled()) { echo $log; exit; }
    }
}

function dump()
{
    $args = func_get_args();
    call_user_func_array(array(Mage::helper('ciphuk_debug'), 'dump'), $args);
}

function backtrace()
{
    $args = func_get_args();
    call_user_func_array(array(Mage::helper('ciphuk_debug'), 'backtrace'), $args);
}

function timer($timer = 'default')
{
    $args = func_get_args();
    call_user_func_array(array(Mage::helper('ciphuk_debug'), 'timer'), $args);
}

function stop()
{
    $args = func_get_args();
    call_user_func_array(array(Mage::helper('ciphuk_debug'), 'stop'), $args);
}