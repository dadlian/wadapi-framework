<?php
	namespace Wadapi\Utility;

	class MessageUtility{
		const DATABASE_CONNECT_ERROR = "Unable to connect to the database. Please check your credentials and connection and try again.";
		const DATABASE_EXECUTE_ERROR = "Failed to successfully communicate with database. Please try again.";
		const DATA_INITIALISATION_ERROR = "The system was unable to initialise required data. Please try again.";
		const DATA_ACCESS_ERROR = "The system was unable to access required data. Please try again.";
		const DATA_MODIFY_ERROR = "The system was unable to modify required data. Please try again.";
		const RESOURCE_UNAVAILABLE_ERROR = "One or more required resources is currently unavailable. Please try again later.";
		const CONFIGURATION_ERROR = "The installed Wadapi instance is misconfigured. Please contact your system administrator.";

		const CACHE_LOAD_WARNING = "The system was unable to cache loaded data.";
		const UNEXPECTED_ARGUMENT_WARNING = "The system received an unexpected argument.";
		const RESOURCE_NOT_READY_WARNING = "One or more system resources was not properly initialised. Please try again.";
	}
?>
