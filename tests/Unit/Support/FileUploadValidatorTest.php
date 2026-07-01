<?php

namespace Platform\Core\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Platform\Core\Support\FileUploadValidator;

class FileUploadValidatorTest extends TestCase
{
    private const MB = 1048576;

    public function test_no_options_means_no_restriction(): void
    {
        // Ohne accept/max_size_mb bleibt alles erlaubt (Default = unveraendert).
        // Wichtig fuer Bestands-Felder anderer Module (HCM), die keine
        // Beschraenkung gesetzt haben.
        $this->assertNull(FileUploadValidator::validate('exe', 'application/octet-stream', 999 * self::MB, []));
    }

    public function test_allowed_extension_passes(): void
    {
        $opts = ['accept' => ['jpg', 'png', 'pdf'], 'max_size_mb' => 10];
        $this->assertNull(FileUploadValidator::validate('pdf', 'application/pdf', 2 * self::MB, $opts));
        $this->assertNull(FileUploadValidator::validate('png', 'image/png', 1 * self::MB, $opts));
    }

    public function test_jpeg_alias_is_accepted_when_jpg_allowed(): void
    {
        $opts = ['accept' => ['jpg', 'png', 'pdf']];
        $this->assertNull(FileUploadValidator::validate('jpeg', 'image/jpeg', 1 * self::MB, $opts));
        $this->assertNull(FileUploadValidator::validate('JPG', 'image/jpeg', 1 * self::MB, $opts));
    }

    public function test_disallowed_extension_is_rejected_with_format_message(): void
    {
        $opts = ['accept' => ['jpg', 'png', 'pdf'], 'max_size_mb' => 10];
        $err = FileUploadValidator::validate('docx', null, 1 * self::MB, $opts);
        $this->assertNotNull($err);
        $this->assertStringContainsStringIgnoringCase('format', $err);
        $this->assertStringContainsString('JPG', $err);
    }

    public function test_missing_extension_with_whitelist_is_rejected(): void
    {
        $opts = ['accept' => ['jpg', 'png', 'pdf']];
        $this->assertNotNull(FileUploadValidator::validate('', null, 1 * self::MB, $opts));
        $this->assertNotNull(FileUploadValidator::validate(null, null, 1 * self::MB, $opts));
    }

    public function test_oversize_file_is_rejected_with_size_message(): void
    {
        $opts = ['accept' => ['jpg', 'png', 'pdf'], 'max_size_mb' => 10];
        // 12 MB > 10 MB
        $err = FileUploadValidator::validate('pdf', 'application/pdf', 12 * self::MB, $opts);
        $this->assertNotNull($err);
        $this->assertStringContainsStringIgnoringCase('groß', $err);
        $this->assertStringContainsString('10', $err);
    }

    public function test_exact_limit_is_allowed(): void
    {
        $opts = ['accept' => ['pdf'], 'max_size_mb' => 10];
        // Genau 10 MB soll noch durchgehen.
        $this->assertNull(FileUploadValidator::validate('pdf', 'application/pdf', 10 * self::MB, $opts));
    }

    public function test_format_is_checked_before_size(): void
    {
        // Falsches Format UND zu groß → Format-Meldung hat Vorrang.
        $opts = ['accept' => ['pdf'], 'max_size_mb' => 10];
        $err = FileUploadValidator::validate('exe', null, 50 * self::MB, $opts);
        $this->assertStringContainsStringIgnoringCase('format', $err);
    }

    public function test_size_only_restriction_without_accept(): void
    {
        // Nur Größenlimit, kein Format-Filter → jedes Format ok, aber Größe greift.
        $opts = ['max_size_mb' => 5];
        $this->assertNull(FileUploadValidator::validate('zip', null, 4 * self::MB, $opts));
        $this->assertNotNull(FileUploadValidator::validate('zip', null, 6 * self::MB, $opts));
    }
}
