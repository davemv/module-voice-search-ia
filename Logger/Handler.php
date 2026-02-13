<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    protected $fileName = '/var/log/ntt_voice_ai.log';
    protected $loggerType = Logger::INFO;
}
