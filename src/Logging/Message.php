<?php
	namespace Wadapi\Logging;

	class Message{
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

		function __construct($initialSession, $initialType, $initialText, $initialSender, $initialRecipient){
			$this->session = $initialSession;
			$this->type = $initialType;
			$this->text = $initialText;
			$this->sender = $initialSender;
			$this->recipient = $initialRecipient;
		}

		function getId(){
			return md5($this->session.$this->type.$this->text.$this->sender.$this->recipient);
		}

		function getType(){
			return $this->type;
		}

		function getText(){
			return $this->text;
		}

		function getSender(){
			return $this->sender;
		}

		function getRecipient(){
			return $this->recipient;
		}
	}
?>
