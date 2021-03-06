<?php
/**
 * ParameterException.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 26.6.13
 */

namespace Keboola\Google\DriveBundle\Exception;

use Keboola\Syrup\Exception\SyrupComponentException;

class ParameterMissingException extends SyrupComponentException
{
	public function __construct($message)
	{
		parent::__construct(400, $message);
	}
}
