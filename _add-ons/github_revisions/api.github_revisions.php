<?php

class API_github_revisions extends API implements Interface_revisions
{

	/**
	 * Determines if this implementation of revisions has been properly configured.
	 *
	 * @return bool
	 */
	public function isProperlyConfigured()
	{
		if (array_get($this->config, 'api_key') && array_get($this->config, 'repo_user') && array_get($this->config, 'repo_name')) {
			return true;
		}

		$this->log->error('An API Key and repository details are required.');

		return false;
	}


	/**
	 * Checks that a given $revision exists for a given $file in the system
	 *
	 * @param string $file     File to check for
	 * @param string $revision Revision key to check
	 * @return bool
	 */
	public function isRevision($file, $revision)
	{
		return true; // @todo
	}


	/**
	 * Checks that a given $revision exists and is the latest revisions for a given $file
	 *
	 * @param string $file     File to check through
	 * @param string $revision Revision to consider as latest
	 * @return bool
	 */
	public function isLatestRevision($file, $revision)
	{
		return $this->core->isLatestRevision($file, $revision);
	}


	/**
	 * Checks to see that a given $file has revisions stored for it
	 *
	 * @param string $file File to check for revisions
	 * @return bool
	 */
	public function hasRevisions($file)
	{
		return $this->core->hasRevisions($file);
	}


	/**
	 * Saves a revision for the given $file with the $content provided, includes a
	 * commit $message and optional $timestamp for back-dating (not required to support)
	 *
	 * @param string $file      File to be saved
	 * @param string $content   The content to be stored to the file
	 * @param string $message   The commit message for this post
	 * @param int    $timestamp An optional timestamp for backdating (not required to support)
	 * @param bool   $is_new    Whether the file is new or not
	 * @return void
	 */
	public function saveRevision($file, $content, $message, $timestamp = null, $is_new = false)
	{
		$this->core->saveRevision($file, $content, $message, $is_new);
	}


	/**
	 * Attempts to save the first revision for a given $file
	 *
	 * @param string $file File that is attempting to save
	 * @return void
	 */
	public function saveFirstRevision($file)
	{
		return; // for now, do nothing. @todo

		// get file contents
		$full_path        = Path::assemble(BASE_PATH, Config::getContentRoot(), $file);
		$existing_content = File::get($full_path);

		// save revision
		$this->saveRevision($file, $existing_content, __('first_save'), File::getLastModified($full_path));
	}


	/**
	 * Deletes all revision history for a given $file
	 *
	 * @param string $file File to delete revisions for
	 * @return void
	 */
	public function deleteRevisions($file)
	{
		// @todo
	}


	/**
	 * Accounts for an $old_file being renamed to $new_file
	 *
	 * @param string  $old_file  Old file name
	 * @param string  $new_file  New file name
	 * @return void
	 */
	public function moveFile($old_file, $new_file)
	{
		// @todo
	}


	/**
	 * Returns the contents of a $file at the given $revision
	 *
	 * @param string $file     The file to look up
	 * @param string $revision The specific revision to grab content from
	 * @return string
	 */
	public function getRevision($file, $revision)
	{
		return $this->core->getRevision($file, $revision);
	}


	/**
	 * Returns the timestamp for when a given $revision of a $file was stored
	 *
	 * @param string $file     The file to look up
	 * @param string $revision The specific revision to grab content from
	 * @return string
	 */
	public function getRevisionTimestamp($file, $revision)
	{
		return $this->core->getRevisionTimestamp($file, $revision);
	}


	/**
	 * Returns the author committing the given $revision for $file
	 *
	 * @param string $file     The file to look up
	 * @param string $revision The specific revision to grab content from
	 * @return string
	 */
	public function getRevisionAuthor($file, $revision)
	{
		return $this->core->getRevisionAuthor($file, $revision);
	}


	/**
	 * Returns an array of revisions for a given $file.
	 *
	 * @param $file
	 * @return array
	 */
	public function getRevisions($file)
	{
		return $this->core->getRevisions($file);
	}
}