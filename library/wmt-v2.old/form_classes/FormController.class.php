<?php 

	require_once FORM_CLASSES . 'FormView.class.php';

	class FormController extends FormView
	{
		private static $formId;
		private static $formName;

		public static $model;
		public static $command;
		public static $definition;
		public static $view;

		public static function init($name, $formId = 0, $formName = null)
		{
			$definition = FormWmt::definition($name);

			self::$formId   = $formId;
			self::$formName = $formName;

			self::$definition   = $definition;
			parent::$definition =& self::$definition;

			self::$model   = FormWmt::model($name);
			self::$command = FormWmt::command($name);

			self::$command->init($formId, $formName, self::$definition);
			self::$model->init(self::$definition, self::$command);

			parent::$command =& self::$command;
			parent::$model   =& self::$model;
		}

		protected static function showMainView($name)
		{
			$dbug = debug_backtrace();
			$childClass = $dbug[1]['class'];
			error_log("Child: (1) [$childClass]");
			$childClass = debug_backtrace()[1]['class'];
			error_log("Child: (2) [$childClass]");
	
			FormWmt::view(
				$name,
				array('controller' => new $childClass)
			);
		}
	}