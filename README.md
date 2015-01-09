Github Revisions (Beta)
======================

> A Github adapter for Statamic's Revisions feature

This add-on allows you to keep revisions of your content in a Github repository using the Github API.

It is free during the beta period.

**What it's good for**
- "Cloud"-based content backups for sites that use FTP or services such as Beanstalk or dploy.io to deploy to their production environments.
- Sites that depend on the control panel for content entry.

**What it's not good for**
- Sites that have use a Git repo as their production environment.  
  This add-on will not modify the local repo, it will modify files directly on Github using their API.
- Sites that have users modifying content directly through files.


## Installation

1. Copy `_add-ons/github_revisions` into your `_add-ons` folder.
2. Copy `_config/github_revisions/github_revisions.yaml` into your `_config` folder
3. Enter your `api_key`, `repo_user` and `repo_name` into the config file.  
   - An API Key can be created by going to Github Settings, Applications, and creating a Personal Access Token.
   - Repo user and name are the two parts of your repo URL respectively. eg. https://github.com/user/name
4. By default, its assumed your content will live in the `_content` folder in the root of your repo. Adjust this by setting `content_root`.
5. Change `_revisions` in `settings.yaml` to `github_revisions`


## Issues / Support

If you come across any bugs or problems, please open a [Github issue](/statamic/github-revisions/issues/new) rather than using the Lodge.