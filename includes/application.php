<?php
/**
 * @package    Cobalt.CRM
 *
 * @copyright  Copyright (C) 2012 Cobalt. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Cobalt Application class
 *
 * Provide many supporting API functions
 *
 * @package    	Cobalt.CRM
 * @subpackage  Application
 * @since       1.5
 */
final class Cobalt extends JApplicationWeb
{
	/**
	 * Currently active template
	 * @var object
	 */
	private $template = null;

	/**
	 * Option to filter by language
	 */
	private $_language_filter = false;

	/**
	 * Option to detect language by the browser
	 */
	private $_detect_browser = false;

	/**
	 * The Application router
	 */
	var $router = null;

	/**
	 * The Unique Application Identifier
	 */
	var $_name = 'Cobalt';

	/** 
	* The Unique Client ID
	*/
	protected $_clientId = null;

	/**
	 * The application message queue.
	 *
	 * @var    array
	 * @since  1.0
	 */
	protected $messageQueue = array();

	public function route(){

		// Get the full request URI.
		$uri = clone JURI::getInstance();

		$router = $this->getRouter();
		$result = $router->parse($uri);

		foreach ($result as $key => $value)
		{
			$this->input->def($key, $value);
		}

		// Trigger the onAfterRoute event.
		JPluginHelper::importPlugin('system');
		$this->triggerEvent('onAfterRoute');
		
	}

	public function __construct(JInput $input = null, JRegistry $config = null, JApplicationWebClient $client = null)
	{
		// Run the parent constructor
		parent::__construct();

		// Register the event dispatcher
		$this->loadDispatcher();

		// Enable sessions by default.
		if (is_null($this->config->get('session')))
		{
			$this->config->set('session', true);
		}

		// Set the session default name.
		if (is_null($this->config->get('session_name')))
		{
			$this->config->set('session_name', $this->_name);
		}

		// Create the session if a session name is passed.
		if ($this->config->get('session') !== false)
		{
			$this->loadSession();

			// Register the session with JFactory
			JFactory::$session = $this->getSession();
		}

		// Register the application to JFactory
		JFactory::$application = $this;
	}

	/**
	 * Provides a secure hash based on a seed
	 *
	 * @param   string  $seed  Seed string.
	 *
	 * @return  string  A secure hash
	 *
	 * @since   11.1
	 */
	public static function getHash($seed)
	{
		return md5(JFactory::getConfig()->get('secret') . $seed);
	}

	/**
	 * Create the user session.
	 *
	 * Old sessions are flushed based on the configuration value for the cookie
	 * lifetime. If an existing session, then the last access time is updated.
	 * If a new session, a session id is generated and a record is created in
	 * the #__sessions table.
	 *
	 * @param   string  $name  The sessions name.
	 *
	 * @return  JSession  JSession on success. May call exit() on database error.
	 *
	 * @since   11.1
	 */
	protected function _createSession($name)
	{
		$options = array();
		$options['name'] = $name;

		if ($this->getCfg('force_ssl') == 2)
		{
			$options['force_ssl'] = true;
		}

		$session = JFactory::getSession($options);

		//TODO: At some point we need to get away from having session data always in the db.
		$db = JFactory::getDBO();

		// Remove expired sessions from the database.
		$time = time();
		if ($time % 2)
		{
			// The modulus introduces a little entropy, making the flushing less accurate
			// but fires the query less than half the time.
			$query = $db->getQuery(true);
			$db->setQuery('DELETE FROM ' . $query->qn('#__session') . ' WHERE ' . $query->qn('time') . ' < ' . (int) ($time - $session->getExpire()));
			$db->query();
		}

		// Check to see the the session already exists.
		if ($this->getCfg('session_handler') == 'database' && $session->isNew())
		{
			$this->checkSession();
		}

		return $session;
	}

	/**
	 * Checks the user session.
	 *
	 * If the session record doesn't exist, initialise it.
	 * If session is new, create session variables
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public function checkSession()
	{
		$db = JFactory::getDBO();
		$session = JFactory::getSession();
		$user = JFactory::getUser();

		$query = $db->getQuery(true);
		$db->setQuery(
			'SELECT ' . $query->qn('session_id') . ' FROM ' . $query->qn('#__session') . ' WHERE ' . $query->qn('session_id') . ' = ' .
			$query->q($session->getId()),
			0, 1
		);
		$exists = $db->loadResult();

		// If the session record doesn't exist initialise it.
		if (!$exists)
		{
			if ($session->isNew())
			{
				$db->setQuery(
					'INSERT INTO ' . $query->qn('#__session') . ' (' . $query->qn('session_id') . ', ' . $query->qn('client_id') . ', ' .
					$query->qn('time') . ')' . ' VALUES (' . $query->q($session->getId()) . ', 1, ' .
					(int) time() . ')'
				);
			}
			else
			{
				$db->setQuery(
					'INSERT INTO ' . $query->qn('#__session') . ' (' . $query->qn('session_id') . ', ' . $query->qn('client_id') . ', ' .
					$query->qn('guest') . ', ' . $query->qn('time') . ', ' . $query->qn('userid') . ', ' . $query->qn('username') . ')' .
					' VALUES (' . $query->q($session->getId()) . ', 1, ' . (int) $user->get('guest') . ', ' .
					(int) $session->get('session.timer.start') . ', ' . (int) $user->get('id') . ', ' . $query->q($user->get('username')) . ')'
				);
			}

			// If the insert failed, exit the application.
			if (!$db->query())
			{
				jexit($db->getErrorMSG());
			}

			// Session doesn't exist yet, so create session variables
			if ($session->isNew())
			{
				$session->set('registry', new JRegistry('session'));
				$session->set('user', new JUser);
			}
		}
	}


	/**
	 * Gets the value of a user state variable.
	 *
	 * @param   string  $key      The key of the user state variable.
	 * @param   string  $request  The name of the variable passed in a request.
	 * @param   string  $default  The default value for the variable if not found. Optional.
	 * @param   string  $type     Filter for the variable, for valid values see {@link JFilterInput::clean()}. Optional.
	 *
	 * @return  The request user state.
	 *
	 * @since   11.1
	 */
	public function getUserStateFromRequest($key, $request, $default = null, $type = 'none')
	{
		$app = JFactory::getApplication();
		$cur_state = $this->getUserState($key, $default);
		$new_state = $app->input->get($request, null, 'default', $type);

		// Save the new value only if it was set in this request.
		if ($new_state !== null)
		{
			$this->setUserState($key, $new_state);
		}
		else
		{
			$new_state = $cur_state;
		}

		return $new_state;
	}

	/**
	 * Gets a user state.
	 *
	 * @param   string  $key      The path of the state.
	 * @param   mixed   $default  Optional default value, returned if the internal value is null.
	 *
	 * @return  mixed  The user state or null.
	 *
	 * @since   11.1
	 */
	public function getUserState($key, $default = null)
	{
		$session = JFactory::getSession();
		$registry = $session->get('registry');

		if (!is_null($registry))
		{
			return $registry->get($key, $default);
		}

		return $default;
	}

	/**
	 * Sets the value of a user state variable.
	 *
	 * @param   string  $key    The path of the state.
	 * @param   string  $value  The value of the variable.
	 *
	 * @return  mixed  The previous state, if one existed.
	 *
	 * @since   11.1
	 */
	public function setUserState($key, $value)
	{
		$session = JFactory::getSession();
		$registry = $session->get('registry');

		if (!is_null($registry))
		{
			return $registry->set($key, $value);
		}

		return null;
	}

	/**
	 * Allows the application to load a custom or default router.
	 *
	 * @param   WebServiceApplicationWebRouter  $router  An optional router object. If omitted, the standard router is created.
	 *
	 * @return  JApplicationWeb This method is chainable.
	 *
	 * @since   1.0
	 */
	public function loadRouter($router = null,$options = null)
	{

		include_once(JPATH_SITE.'/libraries/crm/router.php');
		$this->router = ($router === null) ? new CobaltRouter($options) : $router;

		return $this->router;
	}

	public function doExecute()
	{
		try {
			$this->loadDocument();
			$document = $this->getDocument();
			
			// Register the document object with JFactory
			JFactory::$document = $document;

			// Register the template to the config
			$template = $this->getTemplate(true);
			$this->set('theme', $template->template);
			$this->set('themeFile', $this->input->get('tmpl', 'index') . '.php');

			// Set metadata
			$document->setTitle('Cobalt');

			ob_start();
				require_once JPATH_COBALT.'/cobalt.php';
				$contents = ob_get_contents();
			ob_end_clean();
		
			$document->setBuffer($contents, 'crm');

			// Trigger the onAfterDispatch event.
			JPluginHelper::importPlugin('system');
			$this->triggerEvent('onAfterDispatch');
		}

		// Mop up any uncaught exceptions.
		catch (Exception $e)
		{
			echo $e->getMessage();
			$this->close($e->getCode());
		}
	}
	
	/**
	 * Login authentication function
	 *
	 * @param	array	Array('username' => string, 'password' => string)
	 * @param	array	Array('remember' => boolean)
	 *
	 * @see JApplication::login
	 */
	public function login($credentials, $options = array())
	{

		// Set the application login entry point
		if (!array_key_exists('entry_url', $options))
		{
			$options['entry_url'] = JURI::base().'index.php?view=login';
		}

		// Set the access control action to check.
		$options['action'] = 'core.login.site';

		$app = JFactory::getApplication();

		$authenticate = new ModularAuthenticate();
		return $authenticate::login($credentials, $options);
	}

	public function logout(){

		JSession::checkToken('request') or jexit(JText::_('JInvalid_Token'));

		$app = JFactory::getApplication();
		$authenticate = new ModularAuthenticate();

		// Perform the log in.
		$error = $authenticate::logout();

		// Check if the log out succeeded.
		if (!($error instanceof Exception)) {
			// Get the return url from the request and validate that it is internal.
			$return = base64_decode($app->input->get('return'));
			if (!JURI::isInternal($return)) {
				$return = '';
			}

			// Redirect the user.
			$app->redirect(JRoute::_($return, false));
		} else {
			$app->redirect(JRoute::_('index.php', false));
		}
		
	}

	/**
	 * Gets a configuration value.
	 *
	 * An example is in application/japplication-getcfg.php Getting a configuration
	 *
	 * @param   string  $varname  The name of the value to get.
	 * @param   string  $default  Default value to return
	 *
	 * @return  mixed  The user state.
	 *
	 * @since   11.1
	 */
	public function getCfg($varname, $default = null)
	{
		$config = JFactory::getConfig();
		return $config->get('' . $varname, $default);
	}

	/**
	 * Get the application parameters
	 *
	 * @param	string	The component option
	 * @return	object	The parameters object
	 * @since	1.5
	 */
	public function getParams($option = null)
	{
		static $params = array();

		$hash = '__default';
		if (!empty($option)) {
			$hash = $option;
		}
		if (!isset($params[$hash]))
		{
			// Get component parameters
			if (!$option) {
				$option = $this->input->get('option');
			}
			// Get new instance of component global parameters
			$params[$hash] = new JRegistry;

			// Get language
			$lang_code = JFactory::getLanguage()->getTag();
			// $languages = JLanguageHelper::getLanguages('lang_code');
			$languages = array('en-GB');

			$title = $this->getCfg('sitename');
			if (isset($languages[$lang_code]) && $languages[$lang_code]->metadesc) {
				$description = $languages[$lang_code]->metadesc;
			} else {
				$description = $this->getCfg('MetaDesc');
			}
			$rights = $this->getCfg('MetaRights');
			$robots = $this->getCfg('robots');
			
			$title = '';
			$params[$hash]->def('page_title', $title);
			$params[$hash]->def('page_description', $description);
			$params[$hash]->def('page_rights', $rights);
			$params[$hash]->def('robots', $robots);
		}

		return $params[$hash];
	}

	/**
	 * Get the template
	 *
	 * @return string The template name
	 * @since 1.0
	 */
	public function getTemplate($params = false)
	{
		if(is_object($this->template))
		{
			if ($params) {
				return $this->template;
			}
			return $this->template->template;
		}
		
		// Fallback template
		$template->template = 'default';
		if (!file_exists(JPATH_THEMES . '/default/index.php')) {
			$template->template = '';
		}

		$this->template = $template;
		if ($params) {
			return $template;
		}
		return $template->template;
	}

	public function getMessageQueue()
	{
		if (!count($this->messageQueue))
		{
			$session = JFactory::getSession();
			$sessionQueue = $session->get('application.queue');

			if (count($sessionQueue))
			{
				$this->messageQueue = $sessionQueue;
				$session->set('application.queue', null);
			}
		}

		return $this->messageQueue;
	}

	/**
	 * Overrides the default template that would be used
	 *
	 * @param string	The template name
	 * @param mixed		The template style parameters
	 */
	public function setTemplate($template, $styleParams=null)
	{
		if (is_dir(JPATH_THEMES . '/' . $template)) {
			$this->template = new stdClass;
			$this->template->template = $template;
			if ($styleParams instanceof JRegistry) {
				$this->template->params = $styleParams;
			}
			else {
				$this->template->params = new JRegistry($styleParams);
			}
		}
	}

	/**
	 * Return a reference to the JRouter object.
	 *
	 * @param	string	$name		The name of the application.
	 * @param	array	$options	An optional associative array of configuration settings.
	 *
	 * @return	JRouter
	 * @since	1.5
	 */
	static public function getRouter($name = null, array $options = array())
	{
		$app = JFactory::getApplication();
		$options['mode'] = $app->getCfg('sef');
		$router = $app->loadRouter(null,$options);
		return $router;
	}

	/**
	 * Return the current state of the language filter.
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	public function getLanguageFilter()
	{
		return $this->_language_filter;
	}

	/**
	 * Set the current state of the language filter.
	 *
	 * @return	boolean	The old state
	 * @since	1.6
	 */
	public function setLanguageFilter($state=false)
	{
		$old = $this->_language_filter;
		$this->_language_filter = $state;
		return $old;
	}
	/**
	 * Return the current state of the detect browser option.
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	public function getDetectBrowser()
	{
		return $this->_detect_browser;
	}

	/**
	 * Set the current state of the detect browser option.
	 *
	 * @return	boolean	The old state
	 * @since	1.6
	 */
	public function setDetectBrowser($state=false)
	{
		$old = $this->_detect_browser;
		$this->_detect_browser = $state;
		return $old;
	}

	/**
	 * Redirect to another URL.
	 *
	 * Optionally enqueues a message in the system message queue (which will be displayed
	 * the next time a page is loaded) using the enqueueMessage method. If the headers have
	 * not been sent the redirect will be accomplished using a "301 Moved Permanently"
	 * code in the header pointing to the new location. If the headers have already been
	 * sent this will be accomplished using a JavaScript statement.
	 *
	 * @param	string	The URL to redirect to. Can only be http/https URL
	 * @param	string	An optional message to display on redirect.
	 * @param	string  An optional message type.
	 * @param	boolean	True if the page is 301 Permanently Moved, otherwise 303 See Other is assumed.
	 * @param	boolean	True if the enqueued messages are passed to the redirection, false else.
	 * @return	none; calls exit().
	 * @since	1.5
	 * @see		JApplication::enqueueMessage()
	 */
	public function redirect($url, $msg='', $msgType='message', $moved = false, $persistMsg = true)
	{
		if (!$persistMsg) {
			$this->_messageQueue = array();
		}
		parent::redirect($url, $msg, $msgType, $moved);
	}


	public function getClientId()
	{
		return $this->_clientId;
	}

}
