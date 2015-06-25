<?php
/**
 * Created by Miroslav Čillík <miro@keboola.com>
 * Date: 14/01/14
 * Time: 12:31
 */

namespace Keboola\Google\DriveBundle\Exception;

use Keboola\Syrup\Exception\UserException;

class ConfigurationException extends UserException
{
	public function __construct($message, $previous = null)
	{
		parent::__construct("Wrong configuration: " . $message, $previous);
	}
}
