# Contributing to Algolia for Magento 2

Contributions to the codebase are done using the fork & pull model.
This contribution model has contributors maintaining their own copy of the forked codebase (which can easily be synced with the main copy). The forked repository is then used to submit a request to the base repository to “pull” a set of changes (hence the phrase “pull request”).

Contributions can take the form of new components/features, changes to existing features, tests, bug fixes, optimizations or just good suggestions.

The development team will review all issues and contributions submitted by the community. During the review we might require clarifications from the contributor.


# Contribution requirements

1. Contributions must pass [Continous Integration checks](#continuous-integration-checks).
2. Pull requests (PRs) have to be accompanied by a meaningful description of their purpose. Comprehensive descriptions increase the chances of a pull request to be merged quickly and without additional clarification requests.
3. Commits must be accompanied by meaningful commit messages.
4. PRs which include bug fixing, must be accompanied with step-by-step description of how to reproduce the bug.
5. PRs which include new logic or new features must be submitted along with:
	* Integration test coverage
	* Proposed [documentation](https://community.algolia.com/magento/) update. Documentation contributions can be submitted [here](https://github.com/algolia/magento).
6. All automated tests are passed successfully (all builds on [Travis CI](https://travis-ci.org/algolia/algoliasearch-magento-2/) must be green).

# Contribution process

If you are a new GitHub user, we recommend that you create your own [free github account](https://github.com/signup/free). By doing that, you will be able to collaborate with the Magento 2 development team, “fork” the Magento 2 project and be able to easily send “pull requests”.

1. Fork the repository according to [Fork instructions](https://help.github.com/articles/fork-a-repo/)
2. Create and test your work
	* Write tests
3. Commit your work:
	* Write a [good commit message](http://tbaggery.com/2008/04/19/a-note-about-git-commit-messages.html)
3. When you are ready, send us a pull request
	* Follow [Create a pull request instructions](https://help.github.com/articles/about-pull-requests/)
	* Allow [edits from maintainers](https://blog.github.com/2016-09-07-improving-collaboration-with-forks/)
4. Once your contribution is received, the development team will review the contribution and collaborate with you as needed to improve the quality of the contribution.

# Continuous Integration checks

Automated continous integration checks are run on [Travis CI](https://travis-ci.org/algolia/algoliasearch-magento-2/).

## Integration tests

Integration tests are run via [PHPUnit](https://phpunit.de/) and the extension follows [Magento 2 framework](https://devdocs.magento.com/guides/v2.2/test/integration/integration_test_execution.html) to run integration tests. 

### Setup

1. Copy test's database config to Magento integration tests directory
	```bash
	cp [[extension_root_dir]]/dev/tests/install-config-mysql.php [[magento_root_dir]]/dev/tests/integration/etc/install-config-mysql.php
	```
2. Fill the correct DB credentials to the newly created config file
3. The tests use Algolia credentials from ENV variables: 
	* `ALGOLIA_APPLICATION_ID` (mandatory)
	* `ALGOLIA_SEARCH_API_KEY` (mandatory)
	* `ALGOLIA_API_KEY` (mandatory)
	* `INDEX_PREFIX` (optional, defaults to "magento20tests_")
	* The variable can be set either:
		* Globally by exporting them (`$ export ALGOLIA_APPLICATION_ID=FOO`, repeat for each var)
		* Manually when running the tests (`$ ALGOLIA_APPLICATION_ID=FOO ...other vars... testsRunningCommand`)

### Run

```bash
$ cd [[magento_root_dir]]/dev/tests/integration
$ ../../../vendor/bin/phpunit ../../../vendor/algolia/algoliasearch-magento-2/Test
```

## Coding Style

To check the coding style the extension uses [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer).

The fixer follow Magento 2 default rules and in addition some extra rules defined by the extension's development team. The concrete rules can be found here:
- Magento's default rules - can be found in the root directory of Magento 2 installation in `.php_cs.dist` file
- [Extension's rules](https://github.com/algolia/algoliasearch-magento-2/blob/master/.php_cs)

Definitions of each rule can be found in [the documentation of PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer#usage). 

### Run

**Check:**
```bash
$ cd [[magento_root_dir]]
$ php vendor/bin/php-cs-fixer fix vendor/algolia/algoliasearch-magento-2 --config=vendor/algolia/algoliasearch-magento-2/.php_cs -v --using-cache=no --allow-risky=yes --dry-run
```

**Fix:**
```bash
$ cd [[magento_root_dir]]
$ php vendor/bin/php-cs-fixer fix vendor/algolia/algoliasearch-magento-2 --config=vendor/algolia/algoliasearch-magento-2/.php_cs -v --using-cache=no --allow-risky=yes
```

### Comments (not annotations)

Comments should be used only in rare cases where it really helps others (or your future self) to understand what the code does.

The code itself should be self descriptive. Each time you want to comment a code think first about rewriting the code to be more self explanatory. 
E. g. extract the piece of code to a better named class / method, which will describe what the code does.

**Example of a bad comment:**

```php
/**
 * Method gets user ID
 */
public function getUserId() { ... }
```

**Example of a good comment:**
```php
// In $potentiallyDeletedProductsIds there might be IDs of deleted products which will not be in a collection
if (is_array($potentiallyDeletedProductsIds)) {
    $potentiallyDeletedProductsIds = array_combine(
        $potentiallyDeletedProductsIds,
        $potentiallyDeletedProductsIds
    );
}
```

To learn more about good commenting you can read:
- https://medium.freecodecamp.org/code-comments-the-good-the-bad-and-the-ugly-be9cc65fbf83
- https://improvingsoftware.com/2011/06/27/5-best-practices-for-commenting-your-code/

## Static analysis

For static analysis check the extension uses [Magento Extension Quality Program Coding Standard](https://github.com/magento/marketplace-eqp/) library.
It is a set of rules and sniffs for [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer).

It allows automatically check your code against some of the common Magento and PHP coding issues, like:
- raw SQL queries
- SQL queries inside a loop
- direct class instantiation
- unnecessary collection loading
- excessive code complexity
- use of dangerous functions
- use of PHP superglobals'

This tool is used on official Magento Marketplace and automatically checks the extension during upload.

The tool let the **WARNING**s go, but rejects the extension when **ERROR** appears.

Try to avoid both, warnings and errors, but only **ERROR** prevents a pull request from being merged for now.
This policy may change in a future.

### Setup

Install the tool via [Composer](https://getcomposer.org) next to your Magento instance:
```bash
$ composer create-project --repository=https://repo.magento.com magento/marketplace-eqp magento-coding-standard
```

### Run

```bash
[[magento-coding-standard_dir]]/vendor/bin/phpcs --runtime-set ignore_warnings_on_exit true --ignore=dev,Test [[magento_root_dir]]/vendor/algolia/algoliasearch-magento-2 --standard=MEQP2 --extensions=php,phtml
```
