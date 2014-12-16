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

		return Path::trimSlashes(Path::assemble($content_root, $path));
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

		if ($is_new) {
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
			$message
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
			$this->getBlob($path, $tree_sha)->sha
		);
	}


	/**
	 * Get the latest tree SHA from the API
	 *
	 * @return string SHA hash
	 */
	private function getLatestTreeSha()
	{
		$client = $this->createClient();

		$commits = $client->api('repo')->commits()->all(
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
		// Return the cache if it exists
		$cache_file = 'tree/' . Helper::makeHash($sha);
		if ($this->cache->exists($cache_file)) {
			return $this->cache->getYAML($cache_file);
		}

		$tree = $this->client->api('git')->trees()->show(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$sha,
			true // recursive
		);

		// Save it to cache
		$this->cache->putYAML($cache_file, $tree);

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

		// Return the cache if it exists
		$cache_file = 'blobs/' . Helper::makeHash($path.$sha);
		if ($this->cache->exists($cache_file)) {
			return (object) $this->cache->getYAML($cache_file);
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

		// Save it to cache
		$this->cache->putYAML($cache_file, $blob);

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

		// change commits into a format we can use
		foreach ($commits as $commit) {
			$date = new Carbon($commit['commit']['author']['date']);
			$date = $date->timestamp;

			$revisions[] = array(
				'revision'   => $commit['sha'],
				'message'    => $commit['commit']['message'],
				'timestamp'  => $date,
				'author'     => $commit['author']['login'],
				'is_current' => $this->isCurrentRevision($file, $commit['sha'])
			);
		}

		return $revisions;
	}


	/**
	 * Is a file the current revision?
	 *
	 * @param $file
	 * @param $revision
	 * @return bool
	 */
	public function isCurrentRevision($file, $revision)
	{
		$file_content = File::get(Path::assemble(BASE_PATH, Config::getContentRoot(), $file));
		$blob_content = $this->getBlob($file, $revision)->content;

		return $file_content == $blob_content;
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
		// Return the cache if it exists
		$cache_file = 'commit/' . Helper::makeHash($sha);
		if ($this->cache->exists($cache_file)) {
			return $this->cache->getYAML($cache_file);
		}

		$commit = $this->client->api('repo')->commits()->show(
			$this->config['repo_user'],
			$this->config['repo_name'],
			$sha
		);

		// Save it to cache
		$this->cache->putYAML($cache_file, $commit);

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

		// Return the cache if it exists
		$cache_file = 'commits/' . Helper::makeHash($path);
		if ($this->cache->exists($cache_file)) {
			return $this->cache->getYAML($cache_file);
		}

		// Get commits from api
		$commits = $this->client->api('repo')->commits()->all(
			$this->config['repo_user'],
			$this->config['repo_name'],
			array('path' => $path)
		);

		// Save to cache
		$this->cache->putYAML($cache_file, $commits);

		return $commits;
	}


}