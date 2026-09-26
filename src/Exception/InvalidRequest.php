<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Exception;

/**
 * A request the client refuses to send (1.5.0): a denoiser pin with a value
 * the engine does not accept, a cookie that would inject another one. Nothing
 * reached Trident. A TridentException, so code catching that (as 1.4.x code
 * does for every admin failure) still catches it.
 */
final class InvalidRequest extends TridentException
{
}
