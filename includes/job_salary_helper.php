<?php

if (!function_exists('jobhub_salary_default_currency')) {
    function jobhub_salary_default_currency(): string
    {
        return 'NPR';
    }
}

if (!function_exists('jobhub_salary_default_period')) {
    function jobhub_salary_default_period(): string
    {
        return 'month';
    }
}

if (!function_exists('jobhub_salary_period_options')) {
    function jobhub_salary_period_options(): array
    {
        return [
            'month' => 'Per Month',
            'week' => 'Per Week',
            'day' => 'Per Day',
            'year' => 'Per Year',
        ];
    }
}

if (!function_exists('jobhub_salary_normalize_period')) {
    function jobhub_salary_normalize_period(string $value): ?string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/^per\s+/i', '', $value);

        return match ($value) {
            'day', 'daily' => 'day',
            'week', 'weekly' => 'week',
            'month', 'monthly' => 'month',
            'year', 'yearly', 'annually', 'annual', 'annum' => 'year',
            default => null,
        };
    }
}

if (!function_exists('jobhub_salary_period_label')) {
    function jobhub_salary_period_label(string $period): string
    {
        $normalized = jobhub_salary_normalize_period($period) ?? jobhub_salary_default_period();
        return 'per ' . $normalized;
    }
}

if (!function_exists('jobhub_salary_input_value')) {
    function jobhub_salary_input_value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $formatted = number_format((float) $value, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }
}

if (!function_exists('jobhub_salary_storage_value')) {
    function jobhub_salary_storage_value(mixed $value): ?string
    {
        $input = jobhub_salary_input_value($value);
        if ($input === '') {
            return null;
        }

        return number_format((float) $input, 2, '.', '');
    }
}

if (!function_exists('jobhub_salary_clean_amount_input')) {
    function jobhub_salary_clean_amount_input(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(',', '', $value);
        $value = preg_replace('/\s+/', '', $value);
        return $value;
    }
}

if (!function_exists('jobhub_salary_parse_amount_input')) {
    function jobhub_salary_parse_amount_input(string $value): array
    {
        $clean = jobhub_salary_clean_amount_input($value);
        if ($clean === '') {
            return [
                'ok' => true,
                'value' => null,
                'input' => '',
            ];
        }

        if (!preg_match('/^\d+(?:\.\d+)?$/', $clean)) {
            return [
                'ok' => false,
                'value' => null,
                'input' => $value,
            ];
        }

        return [
            'ok' => true,
            'value' => (float) $clean,
            'input' => jobhub_salary_input_value($clean),
        ];
    }
}

if (!function_exists('jobhub_salary_format_amount')) {
    function jobhub_salary_format_amount(mixed $value): string
    {
        $normalized = jobhub_salary_input_value($value);
        if ($normalized === '') {
            return '';
        }

        $floatValue = (float) $normalized;
        if ((float) (int) $floatValue === $floatValue) {
            return number_format((int) $floatValue);
        }

        $formatted = number_format($floatValue, 2, '.', ',');
        return rtrim(rtrim($formatted, '0'), '.');
    }
}

if (!function_exists('jobhub_salary_format_text')) {
    function jobhub_salary_format_text(mixed $salaryMin, mixed $salaryMax = null, ?string $salaryPeriod = null, ?string $salaryCurrency = null): string
    {
        $min = jobhub_salary_input_value($salaryMin);
        if ($min === '' || (float) $min <= 0) {
            return '';
        }

        $max = jobhub_salary_input_value($salaryMax);
        $period = jobhub_salary_normalize_period((string) $salaryPeriod) ?? jobhub_salary_default_period();
        $currency = strtoupper(trim((string) $salaryCurrency));
        if ($currency === '') {
            $currency = jobhub_salary_default_currency();
        }

        $text = $currency . ' ' . jobhub_salary_format_amount($min);
        if ($max !== '' && (float) $max > 0) {
            $text .= " \u{2013} " . jobhub_salary_format_amount($max);
        }

        return $text . ' ' . jobhub_salary_period_label($period);
    }
}

if (!function_exists('jobhub_salary_extract_period_from_text')) {
    function jobhub_salary_extract_period_from_text(string $value): string
    {
        $value = strtolower(trim($value));

        if (preg_match('/\b(per\s+day|daily)\b/', $value)) {
            return 'day';
        }

        if (preg_match('/\b(per\s+week|weekly)\b/', $value)) {
            return 'week';
        }

        if (preg_match('/\b(per\s+year|yearly|annually|annual|annum)\b/', $value)) {
            return 'year';
        }

        return 'month';
    }
}

if (!function_exists('jobhub_salary_extract_currency_from_text')) {
    function jobhub_salary_extract_currency_from_text(string $value): string
    {
        return preg_match('/\b(npr|rs\.?|rupees?)\b/i', $value)
            ? 'NPR'
            : jobhub_salary_default_currency();
    }
}

if (!function_exists('jobhub_salary_parse_legacy_text')) {
    function jobhub_salary_parse_legacy_text(string $value): array
    {
        $raw = trim($value);
        $default = [
            'parsed' => false,
            'salary_min' => '',
            'salary_max' => '',
            'salary_period' => jobhub_salary_default_period(),
            'salary_currency' => jobhub_salary_default_currency(),
            'legacy_salary' => $raw,
        ];

        if ($raw === '') {
            return $default;
        }

        $working = preg_replace('/[\x{2010}-\x{2015}]+/u', '-', $raw);
        $working = preg_replace('/\b(npr|rs\.?|rupees?)\b/i', '', (string) $working);
        $working = preg_replace('/\b(per\s+day|daily|per\s+week|weekly|per\s+month|monthly|per\s+year|yearly|annually|annual|annum)\b/i', '', (string) $working);
        $working = trim((string) $working);

        if ($working === '' || !preg_match('/^[0-9,\.\-\s]+$/', $working)) {
            return $default;
        }

        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $working, $matches);
        $numbers = $matches[0] ?? [];
        if (count($numbers) < 1 || count($numbers) > 2) {
            return $default;
        }

        $min = jobhub_salary_input_value($numbers[0]);
        $max = count($numbers) >= 2 ? jobhub_salary_input_value($numbers[1]) : '';
        if ($min === '' || (float) $min <= 0) {
            return $default;
        }

        if ($max !== '' && (float) $max < (float) $min) {
            return $default;
        }

        return [
            'parsed' => true,
            'salary_min' => $min,
            'salary_max' => $max,
            'salary_period' => jobhub_salary_extract_period_from_text($raw),
            'salary_currency' => jobhub_salary_extract_currency_from_text($raw),
            'legacy_salary' => '',
        ];
    }
}

if (!function_exists('jobhub_salary_form_values_from_job')) {
    function jobhub_salary_form_values_from_job(array $job): array
    {
        $structuredMin = jobhub_salary_input_value($job['salary_min'] ?? null);
        $structuredMax = jobhub_salary_input_value($job['salary_max'] ?? null);
        $structuredPeriod = jobhub_salary_normalize_period((string) ($job['salary_period'] ?? '')) ?? jobhub_salary_default_period();
        $structuredCurrency = strtoupper(trim((string) ($job['salary_currency'] ?? '')));
        if ($structuredCurrency === '') {
            $structuredCurrency = jobhub_salary_default_currency();
        }

        if ($structuredMin !== '') {
            return [
                'salary_min' => $structuredMin,
                'salary_max' => $structuredMax,
                'salary_period' => $structuredPeriod,
                'salary_currency' => $structuredCurrency,
                'legacy_salary' => '',
            ];
        }

        $parsed = jobhub_salary_parse_legacy_text((string) ($job['salary'] ?? ''));
        if (!empty($parsed['parsed'])) {
            return [
                'salary_min' => (string) $parsed['salary_min'],
                'salary_max' => (string) $parsed['salary_max'],
                'salary_period' => (string) $parsed['salary_period'],
                'salary_currency' => (string) $parsed['salary_currency'],
                'legacy_salary' => '',
            ];
        }

        return [
            'salary_min' => '',
            'salary_max' => '',
            'salary_period' => jobhub_salary_default_period(),
            'salary_currency' => jobhub_salary_default_currency(),
            'legacy_salary' => trim((string) ($job['salary'] ?? '')),
        ];
    }
}

if (!function_exists('jobhub_salary_validate_submission')) {
    function jobhub_salary_validate_submission(array $source, string $legacySalary = ''): array
    {
        $minInput = trim((string) ($source['salary_min'] ?? ''));
        $maxInput = trim((string) ($source['salary_max'] ?? ''));
        $periodInput = trim((string) ($source['salary_period'] ?? jobhub_salary_default_period()));
        $currency = jobhub_salary_default_currency();
        $errors = [];
        $hasSalaryInput = $minInput !== '' || $maxInput !== '';

        $result = [
            'errors' => [],
            'salary_min_input' => $minInput,
            'salary_max_input' => $maxInput,
            'salary_period_input' => $periodInput !== '' ? $periodInput : jobhub_salary_default_period(),
            'salary_currency' => $currency,
            'salary_text' => null,
            'salary_min' => null,
            'salary_max' => null,
            'salary_period' => null,
            'legacy_salary' => $legacySalary,
        ];

        if (!$hasSalaryInput) {
            $result['salary_text'] = $legacySalary !== '' ? $legacySalary : null;
            return $result;
        }

        if ($minInput === '') {
            $errors['salary_min'] = 'Starting salary must be a positive number.';
        }

        $parsedMin = jobhub_salary_parse_amount_input($minInput);
        if ($minInput !== '') {
            if (!$parsedMin['ok'] || $parsedMin['value'] === null || $parsedMin['value'] <= 0) {
                $errors['salary_min'] = 'Starting salary must be a positive number.';
            } else {
                $result['salary_min_input'] = $parsedMin['input'];
                $result['salary_min'] = jobhub_salary_storage_value($parsedMin['value']);
            }
        }

        $parsedMax = jobhub_salary_parse_amount_input($maxInput);
        if ($maxInput !== '') {
            if (!$parsedMax['ok'] || $parsedMax['value'] === null || $parsedMax['value'] <= 0) {
                $errors['salary_max'] = 'Maximum salary must be a positive number.';
            } else {
                $result['salary_max_input'] = $parsedMax['input'];
                $result['salary_max'] = jobhub_salary_storage_value($parsedMax['value']);
            }
        }

        $normalizedPeriod = jobhub_salary_normalize_period($periodInput);
        if ($normalizedPeriod === null) {
            $errors['salary_period'] = 'Please select a valid salary period.';
        } else {
            $result['salary_period_input'] = $normalizedPeriod;
            $result['salary_period'] = $normalizedPeriod;
        }

        if ($result['salary_min'] !== null && $result['salary_max'] !== null && (float) $result['salary_max'] < (float) $result['salary_min']) {
            $errors['salary_max'] = 'Maximum salary must be greater than or equal to starting salary.';
        }

        $result['errors'] = $errors;
        if (!empty($errors)) {
            return $result;
        }

        $result['salary_text'] = jobhub_salary_format_text(
            $result['salary_min'],
            $result['salary_max'],
            (string) $result['salary_period'],
            $currency
        );

        return $result;
    }
}

if (!function_exists('jobhub_salary_storage_columns')) {
    function jobhub_salary_storage_columns(mysqli $conn): array
    {
        static $cache = [];

        $key = $conn->thread_id . ':jobs_salary_columns';
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $columns = [
            'salary_min' => jobhub_column_exists($conn, 'jobs', 'salary_min'),
            'salary_max' => jobhub_column_exists($conn, 'jobs', 'salary_max'),
            'salary_period' => jobhub_column_exists($conn, 'jobs', 'salary_period'),
            'salary_currency' => jobhub_column_exists($conn, 'jobs', 'salary_currency'),
        ];

        $cache[$key] = $columns;
        return $columns;
    }
}

if (!function_exists('jobhub_salary_display_value')) {
    function jobhub_salary_display_value(mixed $salary, string $fallback = 'Not specified'): string
    {
        if (is_array($salary)) {
            $structuredMin = jobhub_salary_input_value($salary['salary_min'] ?? null);
            if ($structuredMin !== '') {
                $formatted = jobhub_salary_format_text(
                    $structuredMin,
                    $salary['salary_max'] ?? null,
                    (string) ($salary['salary_period'] ?? jobhub_salary_default_period()),
                    (string) ($salary['salary_currency'] ?? jobhub_salary_default_currency())
                );
                if ($formatted !== '') {
                    return $formatted;
                }
            }

            $salary = $salary['salary'] ?? '';
        }

        $salary = trim((string) $salary);
        if ($salary === '') {
            return $fallback;
        }

        $parsed = jobhub_salary_parse_legacy_text($salary);
        if (!empty($parsed['parsed'])) {
            $formatted = jobhub_salary_format_text(
                $parsed['salary_min'],
                $parsed['salary_max'],
                $parsed['salary_period'],
                $parsed['salary_currency']
            );
            if ($formatted !== '') {
                return $formatted;
            }
        }

        return $salary;
    }
}
