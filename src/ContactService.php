<?php
declare(strict_types=1);

/**
 * Validation and submission for the contact form (web form and JSON API share this).
 */
final class ContactService
{
    private const MAX_NAME = 100;
    private const MAX_EMAIL = 150;
    private const MAX_SUBJECT = 150;
    private const MAX_MESSAGE = 5000;

    /**
     * Validate raw input.
     *
     * @param array<string, mixed> $raw
     * @return array{data: array{name:string,email:string,subject:string,message:string}, errors: array<string,string>}
     */
    public static function validate(array $raw): array
    {
        $clean = [
            'name'    => self::str($raw['name'] ?? ''),
            'email'   => self::str($raw['email'] ?? ''),
            'subject' => self::str($raw['subject'] ?? ''),
            'message' => self::str($raw['message'] ?? ''),
        ];
        $errors = [];

        if ($clean['name'] === '' || mb_strlen($clean['name']) > self::MAX_NAME) {
            $errors['name'] = 'Please enter your name (max ' . self::MAX_NAME . ' characters).';
        }
        if ($clean['email'] === '' || mb_strlen($clean['email']) > self::MAX_EMAIL
            || filter_var($clean['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($clean['subject'] === '' || mb_strlen($clean['subject']) > self::MAX_SUBJECT) {
            $errors['subject'] = 'Please choose or enter a subject.';
        }
        $len = mb_strlen($clean['message']);
        if ($len < 10 || $len > self::MAX_MESSAGE) {
            $errors['message'] = 'Message must be between 10 and ' . self::MAX_MESSAGE . ' characters.';
        }

        return ['data' => $clean, 'errors' => $errors];
    }

    /**
     * Validate, rate-limit and store a message.
     *
     * @param array<string, mixed> $raw
     * @return array{ok: bool, id?: int, errors?: array<string,string>, status: int, message: string}
     */
    public static function submit(array $raw): array
    {
        // Honeypot: real users never fill the hidden "website" field. Pretend success.
        if (!empty($raw['website'])) {
            return ['ok' => true, 'status' => 201, 'message' => 'Thanks! Your message has been sent.'];
        }

        $result = self::validate($raw);
        if ($result['errors']) {
            return ['ok' => false, 'status' => 422, 'errors' => $result['errors'],
                    'message' => 'Please fix the highlighted fields.'];
        }

        $limit = (array) config('contact_rate_limit', ['max' => 5, 'window_minutes' => 15]);
        $ip = client_ip();
        if (Repository::recentMessageCount($ip, (int) $limit['window_minutes']) >= (int) $limit['max']) {
            return ['ok' => false, 'status' => 429,
                    'message' => 'You have sent several messages recently. Please try again later.'];
        }

        $id = Repository::createMessage($result['data'], $ip, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return ['ok' => true, 'id' => $id, 'status' => 201,
                'message' => 'Thanks! We have received your message and will reply soon.'];
    }

    private static function str(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        // Strip control characters except newlines/tabs.
        $value = preg_replace('/[^\P{C}\n\t]/u', '', (string) $value) ?? '';
        return trim($value);
    }
}
