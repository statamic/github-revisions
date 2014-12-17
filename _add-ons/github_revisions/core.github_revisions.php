<?php

require_once __DIR__.'/vendor/autoload.php';

use \Carbon\Carbon;

class Core_github_revisions extends Core
{

	/**
	 * @var \Github\Client
	 */
	private $client;


	/**
	 * Create the Github client upon initialization. It's used everywhere.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->client = $this->createClient();
	}


	/**
	 * Takes a file path, prepends the content root and trims the slashes
	 *
	 * @param $path
	 * @return string
	 */
	private function standardize($path)
	{
		$content_root = array_get($this->config, 'content_root');

		$path = Path::trimSlashes(Path::assemble($content_root, $path));

		// If an already standardized path is passed in, the content root will double up.
		// Address that.
		$path = str_replace($content_root . '/' . $content_root, $content_root, $path);

		return $path;
	}


	/**
	 * Creates a github client and authenticates it
	 *
	 * @return \Github\Client
	 */
	private function createClient()
	{
		$client = new \Github\Client();
		$client->authenticate($this->config['api_key'], null, Github\Client::AUTH_HTTP_TOKEN);

		return $client;
	}


	/**
	 * Saves a revision of a file
	 *
	 * @param  string $file         File to be committed
	 * @param  string $file_content Contents of the file
	 * @param  string $message      Commit message
	 * @param  bool   $is_new       Whether the file is new or not
	 * @return void
	 */
	public function saveRevision($file, $file_content, $message, $is_new)
	{
		$path = $this->standardize($file);

		if ($files = $this->blink->get('move')) {
			$this->deleteFile($this->standardize($files['old_file']));
		}

		if ($is_new || $files) {
			$this->commitNewFile($path, $file_content, $message);
		} else {
			$this->commitExistingFile($path, $file_content, $message);
		}
	}


	/**
	 * Commits a new file to the repo
	 *
	 * @param $path  File to be committed
	 * @param $file_content  Contents of the file
	 * @param $message  Commit message
	 */
	private function commitNewFile($path, $file_content, $message)
	{
		$this->client->api('repo')->contents()->create(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$path,
			$file_content,
			$message,
			null,
			$this->getCommitter()
		);
	}


	/**
	 * Commits an update to an existing file to the repo
	 *
	 * @param $path  File to be committed
	 * @param $file_content  Contents of the file
	 * @param $message  Commit message
	 */
	private function commitExistingFile($path, $file_content, $message)
	{
		$tree_sha = $this->getLatestTreeSha();

		$this->client->api('repo')->contents()->update(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$path,
			$file_content,
			$message,
			$this->getBlob($path, $tree_sha)->sha,
			null,
			$this->getCommitter()
		);
	}


	/**
	 * Deletes a file from the repo
	 * 
	 * @param $file  File to be deleted
	 * @return void
	 */
	public function deleteFile($file)
	{
		$path = $this->standardize($file);

		$existing_file = $this->getBlob($file, $this->getLatestTreeSha());

		$message = Config::get('_revisions_message_prefix') . ' ';
		$message .= ($this->blink->exists('move')) ? __('file_renamed') : __('file_deleted');

		$this->client->api('repo')->contents()->rm(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$path,
			$message,
			$existing_file->sha
		);
	}


	/**
	 * Get the latest tree SHA from the API
	 *
	 * @return string SHA hash
	 */
	private function getLatestTreeSha()
	{
		$commits = $this->client->api('repo')->commits()->all(
			$this->config['repo_user'],
			$this->config['repo_name'],
			array('sha' => 'master', 'per_page' => 1)
		);

		return $commits[0]['commit']['tree']['sha'];
	}


	/**
	 * Gets a specific tree's data from the API
	 *
	 * @param $sha
	 * @return mixed
	 */
	private function getTree($sha)
	{
		// Return the blink cache if it exists
		$blink_key = 'tree/' . Helper::makeHash($sha);
		if ($this->blink->exists($blink_key)) {
			return $this->blink->get($blink_key);
		}

		$tree = $this->client->api('git')->trees()->show(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$sha,
			true // recursive
		);

		// Save it to blink cache
		$this->blink->set($blink_key, $tree);

		return $tree;
	}


	/**
	 * Get blob from the API
	 *
	 * @param  string $file  Filename to retrieve
	 * @param  string $sha   SHA hash of the commit
	 * @return object        The blob
	 */
	private function getBlob($file, $sha)
	{
		$path = $this->standardize($file);

		// Return the blink cache if it exists
		$blink_key = 'blobs/' . Helper::makeHash($path.$sha);
		if ($this->blink->exists($blink_key)) {
			return (object) $this->blink->get($blink_key);
		}

		// Get the requested tree
		$tree = $this->getTree($sha);

		// Find the requested file in the tree
		foreach ($tree['tree'] as $t) {
			if ($t['path'] == $path) {
				$blob_sha = $t['sha'];
				break;
			}
		}

		// No blob found
		if ( ! isset($blob_sha)) {
			return false;
		}

		// Get the matched blob
		$blob = $this->client->api('git')->blobs()->show(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$blob_sha
		);

		// Pull out its content
		$content = base64_decode($blob['content']);

		// Turn it into more usable data
		$blob = array(
			'tree_sha'  => $blob['sha'],
			'path'      => $path,
			'sha'       => $blob_sha,
			'content'   => $content
		);

		// Save it to blink cache
		$this->blink->set($blink_key, $blob);

		// Return it
		return (object) $blob;
	}


	/**
	 * Checks to see that a given $file has revisions stored for it
	 *
	 * @param string $file File to check for revisions
	 * @return bool
	 */
	public function hasRevisions($file)
	{
		$commits = $this->getCommits($file);

		return ! empty($commits);
	}


	/**
	 * Returns an array of revisions for a given $file.
	 *
	 * @param $file
	 * @return array
	 */
	public function getRevisions($file)
	{
		$revisions = array();

		$commits = $this->getCommits($file);

		$latest_commit_sha = $commits[0]['sha'];

		// change commits into a format we can use
		foreach ($commits as $commit) {
			$date = new Carbon($commit['commit']['author']['date']);
			$date = $date->timestamp;

			$message = $commit['commit']['message'];
			$author = $commit['commit']['committer']['name'];

			$revisions[] = array(
				'revision'   => $commit['sha'],
				'message'    => $message,
				'timestamp'  => $date,
				'author'     => $author,
				'is_current' => Request::get('revision', $latest_commit_sha) == $commit['sha']
			);
		}

		return $revisions;
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
		$path = $this->standardize($file);
		
		foreach ($this->getCommits($path) as $commit) {
			if ($commit['sha'] == $revision) return true;
		}

		return false;
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
		$revisions = $this->getRevisions($file);

		return $revisions[0]['revision'] == $revision;
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
		return $this->getBlob($file, $revision)->content;
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
		$commit = $this->getCommit($revision);

		$date = new Carbon($commit['commit']['author']['date']);

		return $date->timestamp;
	}


	/**
	 * Gets a specific commit's data from the API
	 *
	 * @param $sha
	 * @return mixed
	 */
	private function getCommit($sha)
	{
		// Return the blink cache if it exists
		$blink_key = 'commit/' . Helper::makeHash($sha);
		if ($this->blink->exists($blink_key)) {
			return $this->blink->get($blink_key);
		}

		$commit = $this->client->api('repo')->commits()->show(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$sha
		);

		// Save it to blink cache
		$this->blink->set($blink_key, $commit);

		return $commit;
	}


	/**
	 * Gets an array of commits for a specific file
	 *
	 * @param $file
	 * @return mixed
	 */
	private function getCommits($file)
	{
		$path = $this->standardize($file);

		// Get commits from api
		$commits = $this->client->api('repo')->commits()->all(
			$this->config['repo_user'],
			$this->config['repo_name'],
			array('path' => $path)
		);

		return $commits;
	}


	/**
	 * Generate committer details
	 * 
	 * @return array  An array containing name, email and the current date/time
	 */
	private function getCommitter()
	{
		// Grab the member
		$member = Auth::getCurrentMember();

		$email = $member->get($this->config['committer_email']);
		$date = Carbon::now()->toIso8601String();

		// Build the name from the member fields specified in the config
		$name = implode(' ', array_map(function($field) use ($member) {
			return $member->get($field);
		}, explode(' ', $this->config['committer_name'])));

		// Fall back to username if there is no generated name
		$name = ($name == ' ') ? $member->get('username') : $name;

		return compact('name', 'email', 'date');
	}

}