---
name: spec-driven-tdd
description: Use when implementing any feature or bugfix, before writing implementation code. Enforces Spec-Driven TDD with hard gates.
---

# Spec-Driven Test-Driven Development (SD-TDD)

This skill enforces a rigid, predictable development workflow where test cases are agreed upon and proven to fail (RED) *before* any production code is written.

<HARD-GATE>
You MUST NOT write or modify any production code (files in `src/`, `database/migrations/`, etc.) until Steps 1, 2, and 3 are completely finished and the human partner has explicitly approved Step 3.

Violation of this gate means you are writing tests after the fact, which is forbidden.
</HARD-GATE>

## The Workflow Checklist

When invoked, announce "Entering SD-TDD flow. Starting Step 1." and complete the following steps in exact order. Do not combine steps.

### Step 1: The Board (Spec Definition)
1. Determine what needs to be built or fixed.
2. Define the acceptance criteria as a "Test Cases Board" (a markdown table).
3. The table MUST include:
   - **Test Case Description**: What exact behavior is expected?
   - **Assertion Target**: What specific piece of production code (class, method, config key, option) would break this if removed? (See `writing-good-tests.md`'s mutation check).
4. Present this table to the human partner in chat (or update a Spec document in `docs/superpowers/specs/`).

### Step 2: The Red Light (Write Tests ONLY)
1. Write the tests defined in the Step 1 Board into the `tests/` directory.
2. **DO NOT touch production code yet.**
3. Run the test suite: `vendor/bin/pest` (use `--filter` if needed, but ensure you see the failure).
4. **MANDATORY**: The tests MUST fail. If they pass, they are fake tests or testing framework behavior. Delete them and try again.
5. Provide the human partner with the explicit failing output from Pest.

### Step 3: Human Gate (Approval)
1. Stop and ask the human partner for approval.
2. Example: *"The tests are written and legitimately failing (Red). Here is the output. Do I have your approval to start writing the production code to make them pass?"*
3. **DO NOT PROCEED until the user says "Yes" or gives clear approval.**

### Step 4: The Match-3 Game (Implementation)
1. Only after approval, begin modifying production code.
2. Write the *minimal* code necessary to turn the red lights green.
3. Run the full test suite (`composer test` which includes PHPStan, Pint, Type Coverage, and Pest).
4. Do not modify the tests from Step 2 to force them to pass. If the test was wrong, you must revert to Step 1.
5. When all lights are green, the task is complete.

## Red Flags (Restart the Flow)

- You modified a file in `src/` before getting Step 3 approval. -> **REVERT THE FILE. START OVER.**
- The test passed immediately in Step 2. -> **IT IS A FAKE TEST. REWRITE IT.**
- You are tempted to "just fix the bug real quick while writing the test". -> **STOP. WRITE THE TEST. WATCH IT FAIL.**