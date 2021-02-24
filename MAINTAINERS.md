# Notes for Maintainers

### Testing
- CircleCI test configurations are found in the `.circleci/config.yml` file. For any new Magento versions to test against, you will need to build and push up a new image to be used in CircleCI using this [repository](https://github.com/algolia/magento2-circleci).
- Quality tools can also be upgraded [here](https://github.com/algolia/magento2-tools).

### Approval Process
- All PRs need to have 1 approved reviewer by a maintainer and all testing needs to pass before merging.
- While we have integration testing, we need to manually test all PRs to ensure quality. 

### Release Process 
- Prepare release notes with all the changes and tagging community members for their contribution.
- Make a **bump** PR to update all the version numbers and merge into `develop`.
- After a bump has been created, you can then merge `develop` into `master`.
- Create a release and tag with your release notes.

### Marketplace
You will need to package the release before uploading to the Magento Marketplace. Run `dev/release.sh` in the extension directory to create the zip file.