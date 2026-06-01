<?php

namespace FromDevelopersForDevelopers\RelMonLaravel\Rules;

use FromDevelopersForDevelopers\RelMon\Exception\RelMonException;
use FromDevelopersForDevelopers\RelMon\Exception\ValidationException;
use FromDevelopersForDevelopers\RelMonLaravel\Contracts\RelMonServiceContract;
use Illuminate\Contracts\Validation\Rule;

final class RelMonPayload implements Rule
{
    /**
     * @var array<int, string>
     */
    private array $messages = [];

    public function __construct(
        private ?string $format = null,
        private ?array $defaults = null,
    ) {
    }

    public function passes($attribute, $value)
    {
        $this->messages = [];

        try {
            app(RelMonServiceContract::class)->build($value, $this->format, $this->defaults);
        } catch (ValidationException $exception) {
            $this->messages = $this->validationMessages((string) $attribute, $exception);
        } catch (RelMonException $exception) {
            $this->messages = [$this->fallbackMessage((string) $attribute, $exception->getMessage())];
        }

        return $this->messages === [];
    }

    public function message()
    {
        if ($this->messages === []) {
            return 'The field is not a valid RelMon payload.';
        }

        return count($this->messages) === 1
            ? $this->messages[0]
            : $this->messages;
    }

    private function validationMessages(string $attribute, ValidationException $exception): array
    {
        $messages = [];

        foreach ($exception->getViolations() as $violation) {
            $message = trim($violation->getMessage());

            if ($message === '') {
                continue;
            }

            $messages[] = $this->fallbackMessage($attribute, $message);
        }

        return $messages !== []
            ? $messages
            : [$this->fallbackMessage($attribute)];
    }

    private function fallbackMessage(string $attribute, ?string $details = null): string
    {
        $baseMessage = sprintf('The %s field is not a valid RelMon payload.', $attribute);

        if ($details === null || trim($details) === '') {
            return $baseMessage;
        }

        return sprintf('%s %s', $baseMessage, trim($details));
    }
}
