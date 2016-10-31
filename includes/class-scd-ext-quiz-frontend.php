<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sensei Content Drip ( scd ) Extension Quiz Frontend
 *
 * The class controls all frontend activity relating to blocking access if it is part of a drip campaign
 *
 * @package WordPress
 * @subpackage Sensei Content Drip
 * @category Core
 * @author WooThemes
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 * - __construct
 * - quiz_content_drip_filter
 * - get_quiz_with_updated_content
 * - get_drip_type_message
 * - generate_absolute_drip_type_message
 * - generate_dynamic_drip_type_message
 * - get_quiz_drip_type
 * - get_quiz_lesson_id
 */

class Scd_Ext_Quiz_Frontend {
	/**
	 * The token.
	 *
	 * @var    string
	 * @access private
	 * @since  1.0.0
	 */
	private $_token;

	/**
	 * The message shown in place of quiz content
	 *
	 * @var    string
	 * @access protected
	 * @since  1.0.0
	 */
	protected $drip_message;

	/**
	 * Constructor function
	 */
	public function __construct() {
		// Set a formatted  message shown to user when the content has not yet dripped
		$defaultMessage       = __( 'This quiz will become available on [date].', 'sensei-content-drip' );
		$settingsMessage      = Sensei_Content_Drip()->settings->get_setting( 'scd_drip_quiz_message' );
		$this->message_format = empty( $settingsMessage ) ? $defaultMessage : $settingsMessage;

		// Hook int all post of type quiz to determine if they should be
		add_filter( 'the_posts', array( $this, 'quiz_content_drip_filter' ), 1 );
	}

	/**
	 * Loops through each post page
	 * to confirm if ths content should be hidden
	 *
	 * @since  1.0.0
	 * @param  array $quizzes
	 * @return array
	 * @uses   the_posts()
	 */
	public function quiz_content_drip_filter( $quizzes ) {
		// This should only apply to the front end on single course and quiz pages
		if ( is_admin() || empty( $quizzes ) || 'quiz' !== $quizzes[0]->post_type ) {
			return $quizzes;
		}

		// Loop through each post and replace the content
		foreach ( $quizzes as $index => $quiz ) {
			$lesson_id = $this->get_quiz_lesson_id( $quiz->ID );
			if ( Sensei_Content_Drip()->access_control->is_lesson_access_blocked( $lesson_id ) ) {
				// Change the quiz content accordingly
				$quizzes[ $index ] = $this->get_quiz_with_updated_content( $quiz );
			}
		}

		return $quizzes;
	}

	/**
	 * Replace post content with settings or filtered message
	 * This function acts on the title, content, embedded video and quiz
	 *
	 * @since  1.0.0
	 * @param  WP_Post $quiz
	 * @return WP_Post
	 */
	public function get_quiz_with_updated_content( $quiz ) {
		// Ensure all things are in place before proceeding
		if ( empty( $quiz ) ) {
			return $quiz;
		}

		// Get the compiled message text
		$new_content = $this->get_drip_type_message( $quiz->ID );

		// Wrap the message in sensei notice
		$new_content = '<div class="sensei-message info">' . esc_html( $new_content ) . '</div>';

		/**
		 * Filter the message a user will see when content is not available.
		 *
		 * @since 1.0.0
		 * @param string $drip_message the message
		 */
		$new_content        = apply_filters( 'sensei_content_drip_quiz_message', $new_content );
	    $quiz->post_content = '<p>' . wp_trim_words( $quiz->post_content, 20 ) . '</p>' . $new_content;

		// Set the excerpt to be a trimmed down version of the full content if it is empty
		if ( empty( $quiz->post_excerpt ) ) {
			$quiz->post_excerpt = '<p>' . wp_trim_words( $quiz->post_content , 20 ) . '</p>' . $new_content;
		} else {
			$quiz->post_excerpt = '<p>' .  $quiz->post_excerpt  . '&hellip;</p>' . $new_content;
		}

		// Hide the quiz questions
		remove_all_actions( 'sensei_quiz_questions' );

		// Hide the quiz quiz notice and quiz buttons
		remove_all_actions( 'sensei_pagination' );

		return $quiz;
	}

	/**
	 * Check if  the quiz can be made available to the the user at this point
	 * according to the drip meta data
	 *
	 * @since  1.0.0
	 * @param  string $quiz_id
	 * @return bool
	 */
	public function get_drip_type_message( $quiz_id ) {
	    $message = '';

		// Check that the correct data has been passed
		if ( empty( $quiz_id) ) {
			// Just return the simple message as the exact message can not be determined without the ID
			return $message;
		}

		$drip_type = get_post_meta( $quiz_id , '_sensei_content_drip_type', true );
		if ( 'absolute' === $drip_type ) {
			// Call the absolute drip type message creator function which creates a message dependant on the date
			$message = $this->generate_absolute_drip_type_message( $quiz_id );
		} else if ( 'dynamic' === $drip_type ) {
			// Call the dynamic drip type message creator function which creates a message dependant on the date
			$message = $this->generate_dynamic_drip_type_message( $quiz_id );
		}

		return $message;
	}

	/**
	 * Absolute drip type: converting the formatted messages into a standard string depending on the details passed in
	 *
	 * @since  1.0.0
	 * @param  int $quiz_id
	 * @return bool
	 */
	public function generate_absolute_drip_type_message( $quiz_id ) {
		$absolute_drip_type_message = '';

		// Get this quizs drip data
		$quiz_drip_date = Scd_Ext_Utils::date_from_datestring_or_timestamp( $quiz_id );

		$formatted_date = $quiz_drip_date->format( Sensei_Content_Drip()->get_date_format_string() );

		// Replace the shortcode in the class message_format property set in the constructor
		if ( strpos( $this->message_format , '[date]') ) {
			$absolute_drip_type_message = str_replace( '[date]', $formatted_date , $this->message_format );
		} else {
			$absolute_drip_type_message = $this->message_format . ' ' . $formatted_date;
		}

		return $absolute_drip_type_message;
	}

	/**
	 * dynamic drip type: converting the formatted message into a standard string depending on the details passed in
	 *
	 * @since  1.0.0
	 * @param  int $quiz_id
	 * @return bool
	 */
	public function generate_dynamic_drip_type_message( $quiz_id ) {
		$lesson_id                 = $this->get_quiz_lesson_id( $quiz_id );
		$current_user              = wp_get_current_user();
		$user_id                   = $current_user->ID;
		$dynamic_drip_type_message = '';
		$quiz_available_date       = Sensei_Content_Drip()->access_control->get_lesson_drip_date( $lesson_id , $user_id );
		$formatted_date            = date_i18n( Sensei_Content_Drip()->get_date_format_string( ), $quiz_available_date->getTimestamp() );

		// Replace string content in the class message_format property set in the constructor
		$dynamic_drip_type_message = str_replace('[date]' , $formatted_date , $this->message_format );

		return $dynamic_drip_type_message;
	}

	/**
	 * This function checks the quiz drip type
	 *
	 *
	 * @param  string | int $quiz_id
	 * @return string none, absolute or dynamic
	 */
	public function get_quiz_drip_type( $quiz_id ) {
		// Basics, checking out the passed in quiz object
		if ( empty( $quiz_id) || 'quiz' !== get_post_type( $quiz_id ) ) {
			return 'none';
		}

		// Retrieve the drip type from the quiz
		$drip_type = get_post_meta( $quiz_id , '_sensei_content_drip_type', true );

		// Send back the type string
		return empty( $drip_type ) ? 'none' : $drip_type;
	}

	/**
	 * Search the lesson meta to find which lesson this quiz belongs to
	 *
	 * @since  1.0.1
	 * @param  int $quiz_id
	 * @return int
	 */
	public function get_quiz_lesson_id( $quiz_id ) {
		// Look for the quiz's lesson
		$query_args = array(
			'post_type'  => 'lesson',
			'meta_key'   => '_lesson_quiz',
			'meta_value' => $quiz_id
		);
		$lessons = new WP_Query( $query_args );

		if ( ! isset( $lessons->posts ) || empty( $lessons->posts ) ) {
			return false;
		}

		$quiz_lesson = $lessons->posts[0];

		return $quiz_lesson->ID;
	}
}
