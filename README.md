Github Revisions Adapter
========================


1. Copy `_add-ons/github_revisions` into your `_add-ons` folder.
2. Copy `_config/github_revisions/github_revisions.yaml` into your `_config` folder
3. Enter your `api_key`, `repo_user` and `repo_name` into the config file.  
   - An API Key can be created by going to Github Settings, Applications, and creating a Personal Access Token.
   - Repo user and name are the two parts of your repo URL respectively. eg. https://github.com/user/name
4. By default, its assumed your content will live in the `_content` folder in the root of your repo. Adjust this by setting `content_root`.
5. Change `_revisions` in `settings.yaml` to `github_revisions`
