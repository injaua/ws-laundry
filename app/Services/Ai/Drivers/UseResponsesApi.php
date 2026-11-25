<?php

namespace App\Services\Ai\Drivers;

use RuntimeException;

/** @internal Thrown inside OpenAiCompatibleDriver: the model answers only on the Responses API. */
final class UseResponsesApi extends RuntimeException {}
