<?php
/**
 * Gemini API client wrapper.
 *
 * @package TRT_Gemini_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple client for Gemini generateContent endpoint.
 */
class Trtai_Gemini_Client {

    /**
     * Plugin settings.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Constructor.
     *
     * @param Trtai_Settings|array $settings Settings instance or array.
     */
    public function __construct( $settings ) {
        if ( $settings instanceof Trtai_Settings ) {
            $this->settings = $settings->get_settings();
        } elseif ( is_array( $settings ) ) {
            $this->settings = $settings;
        }
    }

    /**
     * Generate content from Gemini.
     *
     * @param array|string $instruction  System or meta instructions.
     * @param array        $user_payload Structured payload for the user message.
     * @param array        $extra        Additional request params.
     *
     * @return array|WP_Error
     */
    public function generate_content( $instruction, $user_payload, $extra = array() ) {
        $api_key = isset( $this->settings['gemini_api_key'] ) ? $this->settings['gemini_api_key'] : '';
        $model   = isset( $this->settings['gemini_model'] ) && $this->settings['gemini_model'] ? $this->settings['gemini_model'] : 'gemini-2.5-flash';
        $timeout = isset( $this->settings['http_timeout'] ) ? absint( $this->settings['http_timeout'] ) : 60;

        if ( $timeout <= 0 ) {
            $timeout = 60;
        }

        if ( empty( $api_key ) ) {
            return new WP_Error( 'trtai_missing_key', __( 'Gemini API key is missing in settings.', 'trtai' ) );
        }

        $system_instruction = "You are an assistant writing for the site 'Tony Reviews Things'. Blend the tone of The Verge, Engadget, Android Police, and MacRumors: conversational yet professional, with modern tech-journalism clarity. Use varied sentence lengths with medium sentences as the backbone. Lead with a clear intro, use descriptive H2/H3s, and keep paragraphs scannable. Provide guidance for news, how-to guides, reviews, deals, and social content, outlining logical structures for each where relevant. Prioritize accuracy, practical explanations, and production-ready output. Avoid hype, snark, and keyword stuffing. Default to US English and apply this directive across all content types.";

        if ( ! empty( $instruction ) ) {
            if ( is_array( $instruction ) ) {
                $system_instruction .= '\nAdditional directives:' . '\n- ' . implode( '\n- ', array_map( 'wp_strip_all_tags', $instruction ) );
            } else {
                $system_instruction .= '\n' . wp_strip_all_tags( (string) $instruction );
            }
        }

        $payload = array(
            'system_instruction' => array(
                'parts' => array(
                    array( 'text' => $system_instruction ),
                ),
            ),
            'contents'          => array(
                array(
                    'role'  => 'user',
                    'parts' => array(
                        array(
                            'text' => wp_json_encode(
                                array(
                                    'brand'         => 'Tony Reviews Things',
                                    'site_voice'    => 'Verge/Engadget/Android Police hybrid',
                                    'instructions'  => 'Respond with structured JSON as requested. Include HTML fragments inside the JSON when asked. Avoid markdown unless explicitly requested. Ensure the JSON is valid and includes all required keys.',
                                    'payload'       => $user_payload,
                                )
                            ),
                        ),
                    ),
                ),
            ),
            'generationConfig' => wp_parse_args(
                $extra,
                array(
                    'temperature' => 0.7,
                )
            ),
        );

        $endpoint = sprintf( 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode( $model ), rawurlencode( $api_key ) );

        $attempts    = 0;
        $max_attempts = 3;
        $response     = null;

        while ( $attempts < $max_attempts ) {
            $attempts++;

            $response = wp_remote_post(
                $endpoint,
                array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                    ),
                    'body'    => wp_json_encode( $payload ),
                    'timeout' => $timeout,
                )
            );

            if ( ! is_wp_error( $response ) ) {
                break;
            }

            if ( ! $this->is_timeout_error( $response ) ) {
                return $response;
            }

            if ( $attempts < $max_attempts ) {
                sleep( $attempts );
            }
        }

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'trtai_gemini_timeout',
                __( 'The Gemini request timed out. Please try again or increase the timeout in settings.', 'trtai' ),
                $response->get_error_data()
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== (int) $code || empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unexpected response from Gemini.', 'trtai' );
            return new WP_Error( 'trtai_gemini_error', $message, array( 'status' => $code, 'response' => $data ) );
        }

        $text   = $data['candidates'][0]['content']['parts'][0]['text'];
        $parsed = $this->maybe_parse_json_response( $text );

        return array(
            'raw'    => $data,
            'text'   => $text,
            'parsed' => is_array( $parsed ) ? $parsed : null,
        );
    }

    /**
     * Attempt to parse JSON from a Gemini response, even when wrapped in code fences.
     *
     * @param string $text Raw response text.
     * @return array|null
     */
    protected function maybe_parse_json_response( $text ) {
        if ( empty( $text ) || ! is_string( $text ) ) {
            return null;
        }

        $decoded = json_decode( $text, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        $trimmed = trim( $text );

        // Handle fenced JSON blocks (e.g., ```json { ... } ```).
        if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/s', $trimmed, $matches ) ) {
            $decoded = json_decode( $matches[1], true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }

        // Look for the first JSON object in the text.
        if ( preg_match( '/(\{[\s\S]*\})/U', $text, $matches ) ) {
            $decoded = json_decode( $matches[1], true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }

        // Or the first JSON array.
        if ( preg_match( '/(\[[\s\S]*\])/U', $text, $matches ) ) {
            $decoded = json_decode( $matches[1], true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Determine whether a WP_Error represents a timeout or transient network issue.
     *
     * @param WP_Error $error Error object from the HTTP request.
     * @return bool
     */
    protected function is_timeout_error( $error ) {
        if ( ! ( $error instanceof WP_Error ) ) {
            return false;
        }

        $timeout_codes = array(
            'http_request_timeout',
            'connect_timeout',
        );

        if ( in_array( $error->get_error_code(), $timeout_codes, true ) ) {
            return true;
        }

        $message = strtolower( $error->get_error_message() );

        return false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'curl error 28' );
    }
}
