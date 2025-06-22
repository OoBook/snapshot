# Changelog

All notable changes to `snapshot` will be documented in this file

## v2.1.2 - 2025-06-22

### :wrench: Bug Fixes

- improve snapshot handling in HasSnapshot by refining condition checks and ensuring null safety by @OoBook in https://github.com/OoBook/snapshot/commit/d8dd06fff7ca5b4b792e5924fcd47b5dff6f6da5

## v2.1.0 - 2025-04-02

### :rocket: Features

- Introduce LazyRelations trait to enhance snapshot relationship handling and improve load methods by @OoBook in https://github.com/OoBook/snapshot/commit/3b5c68aa04a47fee2683ea0c476eae878db755f2
- add SnapshotLazyRelationsTest to validate lazy loading and relation handling in snapshots by @OoBook in https://github.com/OoBook/snapshot/commit/7219c6a288a9f202be0ae7f8e738a47640d63576

### :memo: Documentation

- add foreign key of related model into README while creating by @web-flow in https://github.com/OoBook/snapshot/commit/46f6414aafdb0b833e1f054edcd16c8de2c634a3

## v2.0.0 - 2025-03-21

🚀 Features

Introduce ConfigureSnapshot trait for snapshot configuration management by @OoBook in https://github.com/OoBook/snapshot/commit/5474698c459facb5307e53ee3c50e260c2d0c554
Add Relationships trait for managing snapshot and source model relationships by @OoBook in https://github.com/OoBook/snapshot/commit/0890a7b6073d56b45e9e8c8ccd65f54b09b60727
Introduce SnapshotFields trait for managing snapshot attributes and relationships by @OoBook in https://github.com/OoBook/snapshot/commit/421d34ea5eded44dfd16c8c1604e7af7b7efa1e8
Refactor HasSnapshot trait into a new Concerns namespace for improved organization and functionality by @OoBook in https://github.com/OoBook/snapshot/commit/ec5c970acde670d4addbcea2c4e85811e79a2248
📝 Documentation

Enhance documentation for HasSnapshot trait with usage examples and configuration options by @OoBook in https://github.com/OoBook/snapshot/commit/2e6dbc1a06151e69349a7dd3dfb37728bd295227
✅ Testing

add comprehensive tests for snapshot methods and relationships by @OoBook in https://github.com/OoBook/snapshot/commit/80867b0ba15c0d168d0defe3c1131ff20fccdf8c
💚 Workflow

Update Laravel versions in GitHub Actions workflow to include Laravel 12 and adjust testbench configurations by @OoBook in https://github.com/OoBook/snapshot/commit/32c3438d51a25e42b17ddb1492bc0e3fdc439332
add Laravel 12 test by @web-flow in https://github.com/OoBook/snapshot/commit/0795df05294af1270a514ad7e13246796176aced
create manual-release.yml by @web-flow in https://github.com/OoBook/snapshot/commit/1b23a5121da1b64592b1c6be60ed930e2d32f99c

## v1.0.5 - 2025-02-16

### :wrench: Bug Fixes

- Add null checks in snapshot source field population by @OoBook in https://github.com/OoBook/snapshot/commit/baa16eb8625cb3e0a96876c2402a0c549b5467cf

## v1.0.4 - 2024-11-29

### :wrench: Bug Fixes

- :bug: change method existance scenario according to public methods by @OoBook in https://github.com/OoBook/snapshot/commit/4461bd5fd03f20fd59ecb7fd464a184fe8b95b6c

## v1.0.3 - 2024-11-15

### :wrench: Bug Fixes

- change getSourceForeignKey's access modifiers to public by @OoBook in https://github.com/OoBook/snapshot/commit/2f4b25fc7a942d4ed1063d6bd01792777300cb08

## v1.0.2 - 2024-10-03

### :wrench: Bug Fixes

- change snapshot data when source reference changed by @OoBook in https://github.com/OoBook/snapshot/commit/a5fd958fce9c7badc1f16bcb605bbee885e9aaee
- remove ArgumentCountError from updating observer by @OoBook in https://github.com/OoBook/snapshot/commit/98c31e3131e5d2e5e8862161a824e82fb7fa730d

### :green_heart: Workflow

- remove 8.0 and laravel 9 support by @OoBook in https://github.com/OoBook/snapshot/commit/2c3453b2c8927551ec2d6da0e7abe8955986f1a1

### :beers: Other Stuff

- Update CHANGELOG file
- update php version as >=8.1 by @OoBook in https://github.com/OoBook/snapshot/commit/d14d5b02f9fa5a7225ee2823e01ecaab7bcc7a5b

## v1.0.1 - 2024-09-30

### :rocket: Features

- add access to relationship methods of source by @OoBook in https://github.com/OoBook/snapshot/commit/cb8b3ce4ac6e6d08445c6e1d10ce4f71dbb55bc9

### :wrench: Bug Fixes

- remove comma from composer by @OoBook in https://github.com/OoBook/snapshot/commit/4032dbd22b9f457c04c5da746ef4c48956c4b0d2
- take back spatie package-tools by @OoBook in https://github.com/OoBook/snapshot/commit/bd9a4a32f4565eaf062c12e4a8c2fcbc63c7d02d

### :beers: Other Stuff

- Update CHANGELOG file
- Update CHANGELOG file
- remove package-tools require by @OoBook in https://github.com/OoBook/snapshot/commit/fc732b1900d017a1247cbe5acd518b5397438989

## v1.0.0 - 2024-09-24

Initial Release.

## v0.0.0  - 2024-09-24

**Full Changelog**: https://github.com/OoBook/snapshot/commits/v0.0.0

## 1.0.0 - 2024-09-24

- Initial release
