.. include:: ../Includes.txt

.. _installation:

============
Installation
============


Download via Extension Manager
------------------------------

In the TYPO3 Backend go to Admin Tools > Extensions. Change in the dropdown on
the top left to 'Get Extensions', enter the extension key 'ew_llxml2xliff' in the
text field below the headline 'Get Extensions' and hit go. In the result list
install the extension by hitting the action for that.


Download via Composer
---------------------

Add evoweb/extender to the require in your composer.json like in the following
example and run 'composer install'.

::

	{
		"require": {
			"typo3/cms-core": "^10.0",
			"evoweb/ew-llxml2xliff": "*",
		}
	}


Alternatively if you have an existing project with a configured composer.json you
can add extender with the command by running 'composer require evoweb/ew-llxml2xliff'.
