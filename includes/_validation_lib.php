<?php

declare(strict_types=1);

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

function load_malaysia_locations(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $jsonPath = __DIR__ . '/../malaysia.json';
    $cache = [];
    if (file_exists($jsonPath)) {
        $decoded = json_decode(file_get_contents($jsonPath), true);
        if (is_array($decoded)) {
            // The file wraps the state array in a top-level "state" key —
            // { "state": [ { "name": ..., "city": [...] }, ... ] } —
            // not a bare array of state objects.
            $cache = $decoded['state'] ?? $decoded;
        }
    }
    return $cache;
}

function find_state_entry(array $locations, string $stateName): ?array
{
    $needle = mb_strtolower(trim($stateName));
    foreach ($locations as $state) {
        if (mb_strtolower(trim((string) ($state['name'] ?? ''))) === $needle) {
            return $state;
        }
    }
    return null;
}

function find_city_entry(array $stateEntry, string $cityName): ?array
{
    $needle = mb_strtolower(trim($cityName));
    foreach ($stateEntry['city'] ?? [] as $city) {
        if (mb_strtolower(trim((string) ($city['name'] ?? ''))) === $needle) {
            return $city;
        }
    }
    return null;
}


function validate_location(string $state, string $city, string $postalCode, array &$errors): void
{
    $locations = load_malaysia_locations();

    $stateEntry = $state !== '' ? find_state_entry($locations, $state) : null;
    if ($state === '') {
        $errors['state'] = 'State is required.';
    } elseif ($stateEntry === null) {
        $errors['state'] = 'Invalid state selected.';
    }

    $cityEntry = null;
    if ($city === '') {
        $errors['city'] = 'City is required.';
    } elseif ($stateEntry !== null) {
        $cityEntry = find_city_entry($stateEntry, $city);
        if ($cityEntry === null) {
            $errors['city'] = "The city '{$city}' does not belong to '{$state}'.";
        }
    }

    if ($postalCode === '') {
        $errors['postcode'] = 'Postcode is required.';
    } elseif ($cityEntry !== null) {
        $validPostcodes = array_map('strval', $cityEntry['postcode'] ?? []);
        if (!in_array($postalCode, $validPostcodes, true)) {
            $errors['postcode'] = "Postal code '{$postalCode}' is not valid for {$city}, {$state}.";
        }
    }
}

function validate_dob(string $dob): ?string
{
    if ($dob === '') {
        return null;
    }

    $dobDate = DateTimeImmutable::createFromFormat('d/m/Y', $dob);
    $dobErrors = DateTimeImmutable::getLastErrors();

    if (!$dobDate || $dobDate->format('d/m/Y') !== $dob || ($dobErrors !== false && ($dobErrors['warning_count'] > 0 || $dobErrors['error_count'] > 0))) {
        return 'Please enter a valid date (dd/mm/yyyy).';
    }

    if ($dobDate > new DateTimeImmutable('today')) {
        return 'Date of birth cannot be in the future.';
    }

    $age = (new DateTimeImmutable('today'))->diff($dobDate)->y;
    if ($age < 13) {
        return 'Must be at least 13 years old.';
    }
    if ($age > 120) {
        return 'Please enter a valid date of birth.';
    }

    return null;
}


function dob_to_iso(string $dob): ?string
{
    if ($dob === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('d/m/Y', $dob);
    return $date ? $date->format('Y-m-d') : null;
}


function validate_person_fields(array $input): array
{
    $errors = [];

    $firstName = trim((string) ($input['first_name'] ?? ''));
    $lastName  = trim((string) ($input['last_name'] ?? ''));
    $email     = strtolower(trim((string) ($input['email'] ?? '')));
    $gender    = strtolower(trim((string) ($input['gender'] ?? 'prefer_not_to_say')));
    $dob       = trim((string) ($input['date_of_birth'] ?? ''));
    $phone     = trim((string) ($input['phone_number'] ?? $input['phone'] ?? ''));
    $address1  = trim((string) ($input['address_line1'] ?? ''));
    $state     = trim((string) ($input['state'] ?? ''));
    $city      = trim((string) ($input['city'] ?? ''));
    $postcode  = trim((string) ($input['postcode'] ?? $input['postal_code'] ?? ''));

    if ($firstName === '') {
        $errors['first_name'] = 'First name is required.';
    } elseif (mb_strlen($firstName) > MAX_NAME_LENGTH) {
        $errors['first_name'] = 'First name cannot exceed ' . MAX_NAME_LENGTH . ' characters.';
    } elseif (!preg_match("/^[\p{L}\s'-]+$/u", $firstName)) {
        $errors['first_name'] = 'First name contains invalid characters.';
    }

    if ($lastName === '') {
        $errors['last_name'] = 'Last name is required.';
    } elseif (mb_strlen($lastName) > MAX_NAME_LENGTH) {
        $errors['last_name'] = 'Last name cannot exceed ' . MAX_NAME_LENGTH . ' characters.';
    } elseif (!preg_match("/^[\p{L}\s'-]+$/u", $lastName)) {
        $errors['last_name'] = 'Last name contains invalid characters.';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (mb_strlen($email) > MAX_EMAIL_LENGTH) {
        $errors['email'] = 'Email address cannot exceed ' . MAX_EMAIL_LENGTH . ' characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }

    if (!in_array($gender, ['male', 'female', 'other', 'prefer_not_to_say'], true)) {
        $errors['gender'] = 'Invalid gender selection.';
    }

    if ($dob === '') {
        $errors['date_of_birth'] = 'Date of birth is required.';
    } else {
        $dobError = validate_dob($dob);
        if ($dobError !== null) {
            $errors['date_of_birth'] = $dobError;
        }
    }

    if ($phone === '') {
        $errors['phone_number'] = 'Phone number is required.';
    } elseif (!preg_match('/^\+?\d{7,15}$/', $phone)) {
        $errors['phone_number'] = 'Enter a valid phone number (e.g., 0123456789 or +60123456789).';
    }

    if (!empty($address1) && mb_strlen($address1) > MAX_ADDRESS_LENGTH) {
        $errors['address_line1'] = 'Address line 1 cannot exceed ' . MAX_ADDRESS_LENGTH . ' characters.';
    }

    validate_location($state, $city, $postcode, $errors);

    return $errors;
}
