@include default
@include sdk

## 9. The public surface of this SDK

The surface is every `public` and `protected` member of every class, interface,
trait and enum under `src/`. `protected` counts: customers subclass
`ClientBase`, and narrowing a `protected` member breaks them. The package is
installed by customers as `marketdataapp/sdk-php`.

Breaking, for this SDK:

- a class, interface, trait, enum or constant removed or renamed
- a parameter removed, reordered, retyped, or made required
- a constructor that gains a required parameter
- a return type or a parameter type narrowed, including a nullable type that
  stops accepting `null`
- a method made `final` or `private`, or a class made `final`
- a different `Exception` subclass thrown for the same failure
- an enum case removed, or its backing value changed

Not breaking: a new optional parameter at the end of the list, a new public
method, a new enum case, a widened union type.

`composer.json` carries no version: the version is the git tag. The bump is
therefore declared in `CHANGELOG.md` and in the pull request description, and
nowhere else.
