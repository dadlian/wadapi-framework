<?php
	namespace Wadapi\Logging;

	use Wadapi\Persistence\PersistentClass;

	class Message extends PersistentClass{
		const ERROR = 'error';
		const WARNING = 'warning';
		const SUCCESS = 'success';
		const DEBUG = 'debug';

		//The session in which the message was generated for persistent delivery
		/** @WadapiString(required=true) */
		protected $session;

		//The message type. (Error, warning, success, etc.)
		/** @WadapiString(required=true, values={'error', 'warning', 'success', 'debug'}) */
		protected $type;

		//The text associated with this message
		/** @Text(required=true) */
		protected $text;

		//The message sender
		/** @WadapiString(required=true) */
		protected $sender;

		//The intended message recipient
		/** @WadapiString */
		protected $recipient;
	}
?>
