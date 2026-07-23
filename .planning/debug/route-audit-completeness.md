---
status: complete
trigger: "Adversarial completeness audit for Phase 01 Composer route-audit fail-closed boundary"
created: 2026-07-23
updated: 2026-07-23
---

# Route Audit Completeness

## Symptoms

- Expected: every supported Composer-bearing workflow, shell, wrapper, evaluator, and inline-PHP route produces a guarded, direct, or explicit unclassified audit record; no such route may silently yield zero records.
- Actual: successive narrow fixes have left uncovered equivalent forms, including nested evaluators, compound shell syntax, inline PHP, PHP options/wrappers, and variable-fed PHP process launches.
- Errors: no runtime exception is required; the failure signature is `records=0` and `routeAuditFailures()=[]` for a staged supported-route fixture that can execute a direct Composer mutation.
- Timeline: discovered during independent reviews after Plans 01-08 and 01-09, while focused/full local suites remained green for their existing fixture matrices.
- Reproduction: build disposable staged Git workflow fixtures spanning the declared route grammar, then require nonempty records and failures for all direct/dynamic/unclassified forms.

## Current Focus

- hypothesis: Confirmed — the route audit recognizes individual examples rather than defining a closed, normalized execution grammar with a Composer-bearing no-record fail-closed invariant.
- next_action: Plan the bounded grammar/no-record closure and its staged fixture matrix; do not claim arbitrary shell or PHP parsing.

## Evidence

- timestamp: 2026-07-23
  source: 01-VERIFICATION.md and 01-REVIEW.md
  observation: The current verified gaps are PHP CLI option/wrapper forms around `php -r` and literal variables passed to inline `system()` or `exec()`.

- timestamp: 2026-07-23
  source: isolated disposable staged Git fixture probe; `php tests/Composer/ComposerPolicyGuardTest.php --route-audit`
  observation: A workflow containing `php -n -r`, `php -d ... -r`, or `env`/`command`/`sudo`/`timeout` wrapped inline PHP produced zero records for direct Composer launches. Exact unwrapped `php --run` was classified, proving positional rather than normalized PHP CLI recognition.

- timestamp: 2026-07-23
  source: isolated disposable staged Git fixture probe
  observation: Literal Composer assignments passed through variables or concatenation to `system`, `exec`, `passthru`, `shell_exec`, or `proc_open` produced zero records both in `php -r` and in a tracked `scripts/*.php` file. The enclosing program is Composer-bearing, but `phpProcessLaunches()` tests only the individual dynamic expression.

- timestamp: 2026-07-23
  source: isolated disposable staged Git fixture probe
  observation: Direct Composer execution had zero records behind shell forms not normalized by `parseInvocation()`/`containsComposerExecutableText()`: `! composer`, `time`/`exec`/`nice`/`stdbuf` prefix forms, `printf install | xargs composer`, backtick substitution, assignment-plus-`$variable` indirection, a `for` body, `trap`, Bash `function name { ...; }`, process-substitution `source`, and `${SHELL:-sh} -c` indirection. Existing exact `if`, parenthesized subshell, brace group, and `name() {}` paths do classify.

- timestamp: 2026-07-23
  source: isolated disposable staged Git fixture probe
  observation: `run: &anchor composer install` is valid workflow YAML that resolves to a direct Composer run but receives zero audit records; `Dockerfile*` `RUN composer install`, `RUN --mount=... composer update`, and JSON-array `CMD ["composer", "install"]` likewise receive zero records. The tracked `Makefile` direct recipe is classified, while its dynamic form is already unclassified.

## Eliminated

- hypothesis: The PHP 8.4 dependency graph itself is invalid.
  reason: Approved live Packagist PHP 8.4 resolver/install/audit proof passed.

## Resolution

- root_cause: The route audit is an example-driven collection of lexical recognizers, not a closed normalized route grammar. Its no-record fallback is keyed to whether `composer` is in executable position after a few exact normalizations. Any supported execution form that moves Composer into an argument, a variable, a YAML expansion, a Docker instruction, or a PHP value bypasses both the direct classifier and the fallback, yielding `records=[]` and `routeAuditFailures()=[]`.
- fix: Define and implement the finite grammar and no-record invariant below; this audit applies no source fix.
- verification: The current focused suite and production audit pass but are insufficient: the production audit reports only the three guarded CI/README records. All findings above were reproduced in a temporary staged Git repository and its temporary directory was removed.
- files_changed: `.planning/debug/route-audit-completeness.md` only.

## Root-Cause Report: Finite Closure Strategy

### Required invariant

For every tracked route source in the supported provenance set, each candidate execution node that is either (a) a direct Composer/guard invocation after normalization, or (b) contains a Composer/guard/evaluator marker but cannot be reduced by this grammar, must create exactly one `supported`, `unsupported`, or `unclassified` record. A candidate route may not be discarded solely because Composer is no longer argv[0]. The final source-level safety net is: a Composer/evaluator-bearing command/program/instruction with no child record emits one deterministic `unclassified` record with source path, line, raw source, source kind, and rejection reason.

This is deliberately a finite recognizer, not a promise to parse arbitrary shell, YAML, Docker, Make, or PHP.

### Normalized grammar to support

1. **Provenance nodes:** workflow `run` scalars (including legal YAML scalar/anchor spelling), executable shell lines, Make recipes, Dockerfile `RUN` and exec/shell-form `CMD`/`ENTRYPOINT`, and PHP process-launch calls. Preserve the physical source path and line before any scalar decoding.
2. **Shell nodes:** quote/escape-aware command lists joined by `;`, `&&`, `||`, `|`, `&`; accepted control prefixes `if`/`then`/`elif`/`else`/`fi`, `!`, `do`/`done`, and bounded `for`/`while`/`until`/`case` bodies; exact brace groups, `name() { ...; }`, and Bash `function name { ...; }`; parenthesized subshells. Every unrecognized compound that bears a marker is one unclassified node, not a silent token stream.
3. **Direct invocation normalization:** leading assignments; current `env`, `command`, `sudo`, and `timeout` option grammars; PHP executable aliases (`php`, `phpX.Y`); and an explicit finite set of shell prefix wrappers if they are supported (`exec`, `time`, `nice`, `stdbuf`). `xargs`, `find -exec`, `source`/`.` with process substitution, alias expansion, command substitution/backticks, and executable-variable indirection should either receive their own bounded literal rule or be rejected as unclassified whenever their source subtree contains a marker. Do not try to execute expansion.
4. **Evaluator nodes:** retain the current exact `bash|sh|zsh -c` and one-argument `eval` recursion/limits. Dynamic, concatenated, alternate-option, extra-argv, substituted, or indirect evaluator payloads remain unclassified. Wrapper normalization must occur before evaluator recognition.
5. **PHP CLI nodes:** normalize the same wrapper prefix grammar, then scan PHP CLI options left-to-right until a single `-r`/`--run` program boundary. Support the finite execution-preserving options used by routes (`-n`, `-d value`, `-dvalue`, `-c value`, `-z value`, plus explicit PHP-version executable names); reject all other option/argument layouts containing a marker as unclassified. Do not model arbitrary PHP CLI behavior.
6. **PHP programs:** tokenize without execution. Support literal first arguments to `system`, `exec`, `passthru`, `shell_exec`, and `proc_open` (including the existing literal array form), then recurse to the shell grammar. If a literal program is Composer/evaluator-bearing and any recognized launch is dynamic, concatenated, indirect, malformed, over limit, or yields no record, emit `unclassified-php`; this covers variables, concatenation, `call_user_func`, variable functions, and unmodeled launch APIs without data-flow evaluation.

### Required regression fixture matrix

Use disposable staged Git roots. For each negative form assert `records !== []` and `routeAuditFailures() !== []`; for guarded literal forms assert the shared command contract creates `supported` evidence.

| Boundary | Representative equivalence classes |
| --- | --- |
| Shell lists/control/compound | lists/pipelines/background; `!`; `if`, `for`, `while`, `case`; subshell; brace; POSIX and Bash-function spellings; malformed/unbalanced compound |
| Shell wrappers/indirection | assignments; `env`, `command`, `sudo`, `timeout`; accepted prefix wrappers; `$cmd`, `${SHELL:-sh}`, alias-enabled shell, backticks/`$()`, `xargs`, `find -exec`, `source` process substitution |
| Evaluators | literal/nested `bash`/`sh`/`zsh -c` and `eval`; all dynamic/concatenated/missing/extra/alternate-option forms; depth, payload-count, and length bounds |
| PHP CLI | exact and wrapped `php`/`php8.4`; `-n`, separate/attached `-d`, `-c`, `-z`, `-r`, `--run`; malformed/extra/unsupported option layouts |
| Inline and source PHP | all five literal launch APIs; guarded literal; variable, concatenated, indirect/callable/dynamic launch values; missing/malformed launch; launch-count and program-length bounds |
| Provenance | workflow inline/block/quoted/anchor/alias scalar; shell script; Make recipe; Docker `RUN`, shell/exec `CMD`/`ENTRYPOINT`; PHP file; source outside the approved set must be classified unclassified rather than treated as trusted |

### Precise exclusions

The audit must not claim shell expansion, alias execution, command-substitution evaluation, redirection/here-document semantics, arbitrary YAML anchor resolution, Docker/Make interpretation, Composer-script traversal, PHP variable/data-flow/constant-folding, `eval`, include/autoload, reflection/callable dispatch, arbitrary process APIs, or arbitrary PHP CLI option parsing. Those constructs are rejected as a single source-provenanced `unclassified` record when a bounded marker/sentinel is present. Inputs with no Composer/guard/evaluator marker are outside this route-audit detector and remain unclassified only when their source kind itself is prohibited by project policy.
