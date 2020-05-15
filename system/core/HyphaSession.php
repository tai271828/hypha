<?php

	/**
	 * HyphaSession manages the PHP session for the current request.
	 * It should always be used instead of direct access to
	 * $_SESSION.
	 */
	class HyphaSession {
		var $locked = false;

		public function __construct($request) {
			if (ini_get('session.auto_start'))
				throw new LogicException("session.autostart must be disabled");

			if (session_status() == PHP_SESSION_ACTIVE)
				throw new LogicException("session must not be started before calling HyphaSession");

			// Store the session token in a cookie with a
			// custom name, limited to our root path
			// (prevents sharing the session with other PHP
			// running on the same domain), and is secure
			// (not sent over HTTP) if appropriate, and
			// *only* sent on http(s) requests (not
			// accessible to scripts).
			session_name('hyphaSession');
			ini_set('session.cookie_path', $request->getRootUrlPath());
			ini_set('session.cookie_secure', $request->isSecure());
			ini_set('session.cookie_httponly', true);
			// This enables browser protections against CSRF
			// attacks by omitting this cookie from all
			// requests generated by other sites. Only works
			// in PHP7.3+
			ini_set('session.cookie_samesite', 'Strict');

			// Only load the session when an id cookie is
			// present, to prevent creating a session unless
			// it is needed
			if (isset($_COOKIE[session_name()])) {
				// Load the session
				session_start();

				// If loading the session failed, clear
				// out the session id. Otherwise, it
				// could keep failing e.g. in case the
				// permissions on an existing session
				// are changed. In this case, even
				// regeneration_session_id seems to
				// refuse. Unsetting the cookie forces a
				// new session to be started.
				// In PHP7.1, we could look at the
				// session_start return value instead.
				if (session_status() != PHP_SESSION_ACTIVE) {
					unset($_COOKIE[session_name()]);
				}

				// And close it immediately again to
				// unlock and allow other requests in
				// the same session to also open it. This
				// intentionally writes the session (rather than
				// using e.g. session_abort() to just close it),
				// to make sure that the session is kept alive
				// and not expires while it is being used.
				session_write_close();
			}
		}

		/**
		 * Retrieve a value from the session.
		 *
		 * Must be called while the session is locked.
		 *
		 * This should always be used rather than accessing
		 * $_SESSION directly.
		 */
		public function get($key, $default = null) {
			return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
		}

		/**
		 * Change a value in the session.
		 *
		 * Must be called while the session is locked.
		 *
		 * This should always be used rather than accessing
		 * $_SESSION directly.
		 */
		public function set($key, $value) {
			if (!$this->locked)
				throw new LogicException('Cannot set session value, session not locked');
			$_SESSION[$key] = $value;
		}

		/**
		 * Remove a value from the session.
		 *
		 * Must be called while the session is locked.
		 *
		 * This should always be used rather than accessing
		 * $_SESSION directly.
		 */
		public function remove($key) {
			if (!$this->locked)
				throw new LogicException('Cannot remove session value, session not locked');
			unset($_SESSION[$key]);
		}

		/**
		 * Locks the session and reloads it. This must be called
		 * before changing any values. After changing values, be
		 * sure to unlock as soon as possible again.
		 */
		public function lockAndReload() {
			if ($this->locked)
				throw new LogicException('Cannot lock session, already locked');
			session_start();
			$this->locked = true;
		}

		/**
		 * Writes the session to disk and unlocks it.
		 */
		public function writeAndUnlock() {
			if (!$this->locked)
				throw new LogicException('Cannot unlock session, not locked');
			session_write_close();
			$this->locked = false;
		}

		/**
		 * Unlocks the session without saving any modified
		 * values. Reloads the values from disk, to make sure
		 * any unsaved changes are actually discarded.
		 */
		public function unlockAndReload() {
			if ($this->locked)
				throw new LogicException('Cannot unlock session, not locked');
			session_reset();
			session_abort();
			$this->locked = false;
		}

		/**
		 * Regenerates the id of the session. Should be used
		 * on every login, or whenever other privileges are
		 * stored into the session, to prevent session fixation
		 * attacks.
		 *
		 * This deletes the existing session from disk, but
		 * keeps the existing session's data.
		 *
		 * Must be called while the session is locked.
		 */
		public function changeSessionId() {
			if (!$this->locked)
				throw new LogicException('Cannot change session id, session not locked');
			session_regenerate_id(/* delete old session */ true);
		}
	}
