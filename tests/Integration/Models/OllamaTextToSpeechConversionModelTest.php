<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Models;

use Fueled\AiProviderForOllama\Models\OllamaTextToSpeechConversionModel;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Tests for OllamaTextToSpeechConversionModel.
 *
 * @covers \Fueled\AiProviderForOllama\Models\OllamaTextToSpeechConversionModel
 */
class OllamaTextToSpeechConversionModelTest extends TestCase {

	/**
	 * Model under test.
	 *
	 * @var OllamaTextToSpeechConversionModel
	 */
	private OllamaTextToSpeechConversionModel $model;

	/**
	 * Shared mock transporter for request/response inspection.
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'OLLAMA_HOST=http://localhost:11434' );

		$model_metadata    = new ModelMetadata( 'phonon', 'phonon', array(), array() );
		$provider_metadata = new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, null );

		$this->model       = new OllamaTextToSpeechConversionModel( $model_metadata, $provider_metadata );
		$this->transporter = new MockHttpTransporter();

		$this->model->setHttpTransporter( $this->transporter );
		$this->model->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
	}

	protected function tearDown(): void {
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	/**
	 * Builds a single-message user prompt.
	 *
	 * @param string $text Prompt text.
	 * @return list<Message>
	 */
	private function make_prompt( string $text ): array {
		return array(
			new Message(
				MessageRoleEnum::user(),
				array( new MessagePart( $text ) )
			),
		);
	}

	/**
	 * Builds a WAV file the way Ollama streams it, with both size fields at their maximum value.
	 *
	 * @param string $samples The raw PCM samples.
	 * @return string The WAV file contents.
	 */
	private function make_streamed_wav( string $samples ): string {
		return 'RIFF' . pack( 'V', 0xFFFFFFFF ) . 'WAVE'
			. 'fmt ' . pack( 'V', 16 ) . pack( 'vvVVvv', 1, 1, 24000, 48000, 2, 16 )
			. 'data' . pack( 'V', 0xFFFFFFFF ) . $samples;
	}

	/**
	 * Builds a successful speech response.
	 *
	 * @param string $body The audio bytes.
	 * @return Response
	 */
	private function make_audio_response( string $body ): Response {
		return new Response( 200, array( 'Content-Type' => 'audio/wav' ), $body );
	}

	/**
	 * Tests request construction and parsing of the audio into an inline WAV file.
	 */
	public function test_convert_sends_expected_request_and_returns_wav_file(): void {
		$this->transporter->set_response_to_return( $this->make_audio_response( $this->make_streamed_wav( 'abcd' ) ) );

		$result = $this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello from WordPress.' ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertTrue( $request->getMethod()->isPost() );
		$this->assertSame( 'http://localhost:11434/v1/audio/speech', $request->getUri() );
		$this->assertSame( 'application/json', $request->getHeaderAsString( 'Content-Type' ) );
		$this->assertSame(
			array(
				'model'           => 'phonon',
				'input'           => 'Hello from WordPress.',
				'response_format' => 'wav',
			),
			$request->getData()
		);
		$this->assertNotNull( $request->getOptions() );
		$this->assertSame( 120.0, $request->getOptions()->getTimeout() );

		$file = $result->toFile();
		$this->assertSame( 'audio/wav', $file->getMimeType() );
		$this->assertTrue( $file->isInline() );
		$this->assertStringStartsWith( 'ollama-tts-', $result->getId() );
	}

	/**
	 * Tests that the configured voice is sent to the API.
	 */
	public function test_convert_sends_configured_voice(): void {
		$this->model->setConfig( ModelConfig::fromArray( array( 'outputSpeechVoice' => 'Zoey' ) ) );
		$this->transporter->set_response_to_return( $this->make_audio_response( $this->make_streamed_wav( 'abcd' ) ) );

		$this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertSame( 'Zoey', $request->getData()['voice'] ?? null );
	}

	/**
	 * Tests that text parts across messages are joined into one input.
	 */
	public function test_convert_joins_text_across_messages(): void {
		$this->transporter->set_response_to_return( $this->make_audio_response( $this->make_streamed_wav( 'abcd' ) ) );

		$prompt   = $this->make_prompt( 'First paragraph.' );
		$prompt[] = new Message( MessageRoleEnum::user(), array( new MessagePart( 'Second paragraph.' ) ) );

		$this->model->convertTextToSpeechResult( $prompt );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertSame( "First paragraph.\nSecond paragraph.", $request->getData()['input'] ?? null );
	}

	/**
	 * Tests that the WAV header sizes are replaced with the real lengths.
	 */
	public function test_convert_fixes_streamed_wav_sizes(): void {
		$samples = str_repeat( "\x01\x00", 50 );
		$this->transporter->set_response_to_return( $this->make_audio_response( $this->make_streamed_wav( $samples ) ) );

		$result = $this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );

		$wav = (string) base64_decode( (string) $result->toFile()->getBase64Data() );
		$this->assertSame( 44 + strlen( $samples ), strlen( $wav ) );
		$this->assertSame( strlen( $wav ) - 8, unpack( 'V', substr( $wav, 4, 4 ) )[1] );
		$this->assertSame( strlen( $samples ), unpack( 'V', substr( $wav, 40, 4 ) )[1] );
		$this->assertSame( $samples, substr( $wav, 44 ) );
	}

	/**
	 * Tests that chunks before the audio data are skipped when fixing the sizes.
	 */
	public function test_convert_fixes_wav_sizes_after_extra_chunks(): void {
		$samples = 'abcdef';
		$wav     = 'RIFF' . pack( 'V', 0xFFFFFFFF ) . 'WAVE'
			. 'fmt ' . pack( 'V', 16 ) . pack( 'vvVVvv', 1, 1, 24000, 48000, 2, 16 )
			. 'LIST' . pack( 'V', 3 ) . 'xyz' . "\x00"
			. 'data' . pack( 'V', 0xFFFFFFFF ) . $samples;
		$this->transporter->set_response_to_return( $this->make_audio_response( $wav ) );

		$result = $this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );

		$fixed = (string) base64_decode( (string) $result->toFile()->getBase64Data() );
		$this->assertSame( strlen( $samples ), unpack( 'V', substr( $fixed, 52, 4 ) )[1] );
	}

	/**
	 * Tests that a body that is not a WAV file is returned untouched.
	 */
	public function test_convert_leaves_non_wav_body_unchanged(): void {
		$this->transporter->set_response_to_return( $this->make_audio_response( 'not a wav file' ) );

		$result = $this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );

		$this->assertSame( 'not a wav file', base64_decode( (string) $result->toFile()->getBase64Data() ) );
	}

	/**
	 * Tests that custom options are passed through, except the transport-only timeouts.
	 */
	public function test_convert_passes_custom_options_but_not_timeouts(): void {
		$this->model->setConfig(
			ModelConfig::fromArray(
				array(
					'customOptions' => array(
						'keep_alive'             => '10m',
						'ollama.request_timeout' => 45,
					),
				)
			)
		);
		$this->transporter->set_response_to_return( $this->make_audio_response( $this->make_streamed_wav( 'abcd' ) ) );

		$this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertSame( '10m', $request->getData()['keep_alive'] ?? null );
		$this->assertArrayNotHasKey( 'ollama.request_timeout', (array) $request->getData() );
		$this->assertNotNull( $request->getOptions() );
		$this->assertSame( 45.0, $request->getOptions()->getTimeout() );
	}

	/**
	 * Tests that a custom option cannot override a parameter.
	 */
	public function test_convert_rejects_conflicting_custom_option(): void {
		$this->model->setConfig( ModelConfig::fromArray( array( 'customOptions' => array( 'input' => 'Other text.' ) ) ) );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The custom option "input" conflicts with an existing parameter.' );
		$this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );
	}

	/**
	 * Tests that an unsupported output MIME type is rejected before any request is sent.
	 */
	public function test_convert_rejects_unsupported_mime_type(): void {
		$this->model->setConfig( ModelConfig::fromArray( array( 'outputMimeType' => 'audio/mpeg' ) ) );

		try {
			$this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );
			$this->fail( 'Expected an InvalidArgumentException.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'audio/mpeg', $e->getMessage() );
		}

		$this->assertSame( 0, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that a prompt without text is rejected.
	 */
	public function test_convert_rejects_prompt_without_text(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The prompt must contain text to convert to speech.' );
		$this->model->convertTextToSpeechResult( array() );
	}

	/**
	 * Tests that the input limit is counted in characters, not bytes.
	 */
	public function test_convert_accepts_multibyte_text_at_the_limit(): void {
		$this->transporter->set_response_to_return( $this->make_audio_response( $this->make_streamed_wav( 'abcd' ) ) );

		$this->model->convertTextToSpeechResult( $this->make_prompt( str_repeat( 'é', 4096 ) ) );

		$this->assertSame( 1, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that text over the limit is rejected before any request is sent.
	 */
	public function test_convert_rejects_text_over_the_limit(): void {
		try {
			$this->model->convertTextToSpeechResult( $this->make_prompt( str_repeat( 'a', 4097 ) ) );
			$this->fail( 'Expected an InvalidArgumentException.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( '4096', $e->getMessage() );
		}

		$this->assertSame( 0, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that an empty response body throws.
	 */
	public function test_convert_throws_for_empty_body(): void {
		$this->transporter->set_response_to_return( $this->make_audio_response( '' ) );

		$this->expectException( ResponseException::class );
		$this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );
	}

	/**
	 * Tests that Ollama's error message reaches the exception, such as for an unknown voice.
	 */
	public function test_convert_surfaces_api_error_message(): void {
		$this->transporter->set_response_to_return(
			new Response(
				400,
				array(),
				'{"error":{"message":"voice \"alloy\" not found; available voices: Zoey, default","type":"invalid_request_error"}}'
			)
		);

		$this->expectException( ClientException::class );
		$this->expectExceptionMessage( 'voice "alloy" not found' );
		$this->model->convertTextToSpeechResult( $this->make_prompt( 'Hello.' ) );
	}
}
