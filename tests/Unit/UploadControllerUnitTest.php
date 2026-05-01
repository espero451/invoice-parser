<?php

namespace Tests\Unit;

use App\Http\Controllers\UploadController;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class UploadControllerUnitTest extends TestCase
{
    #[Test]
    public function it_extracts_text_content_from_string_message(): void
    {
        $controller = new UploadController();
        $method = (new ReflectionClass($controller))->getMethod('extractMessageContent');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'choices' => [
                [
                    'message' => [
                        'content' => 'plain text',
                    ],
                ],
            ],
        ]);

        $this->assertSame('plain text', $result);
    }

    #[Test]
    public function it_extracts_text_content_from_message_parts(): void
    {
        $controller = new UploadController();
        $method = (new ReflectionClass($controller))->getMethod('extractMessageContent');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            'choices' => [
                [
                    'message' => [
                        'content' => [
                            ['type' => 'text', 'text' => 'line one'],
                            ['type' => 'text', 'text' => 'line two'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame("line one\nline two", $result);
    }

    #[Test]
    public function it_detects_supported_image_mime_types(): void
    {
        $controller = new UploadController();
        $method = (new ReflectionClass($controller))->getMethod('isImageMime');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, 'image/png'));
        $this->assertFalse($method->invoke($controller, 'application/pdf'));
    }
}
