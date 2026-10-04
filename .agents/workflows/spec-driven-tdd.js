export const meta = {
  name: 'spec-driven-tdd',
  description: 'Deterministic Spec-Driven TDD engine: Spec Board -> Mandatory Red Light Verification -> Implementation -> Quality Gate',
  whenToUse: 'Use when implementing features or bugfixes under the rigid Spec-Driven TDD methodology with physical failure routing.',
  phases: [
    { title: 'The Board', detail: 'Architect analyzes requirements and produces the Spec with a Test Cases Board' },
    { title: 'Red Light Check', detail: 'Write tests ONLY and mechanically verify they FAIL (RED) against current code' },
    { title: 'Implementation', detail: 'Engineer writes minimal production code to turn all red lights green' },
    { title: 'Gate Verification', detail: 'Run composer test full suite (PHPStan, Pint, Type Coverage, Pest)' }
  ]
}

// ---------------------------------------------------------------------------
// 0. Parse arguments & task description
// ---------------------------------------------------------------------------
const TASK = typeof args === 'string' ? args : (args && args.task ? args.task : JSON.stringify(args || 'Implement requested changes following SD-TDD'))

log(`🚀 Starting Spec-Driven TDD workflow for task: "${TASK.slice(0, 80)}..."`)

// ---------------------------------------------------------------------------
// Phase 1: The Board (Architect designs Spec and Test Cases Board)
// ---------------------------------------------------------------------------
phase('The Board')
log('📐 Phase 1: Dispatching Architect to survey codebase and create Test Cases Board...')

const BOARD_SCHEMA = {
  type: 'object',
  required: ['specPath', 'testCases', 'summary'],
  properties: {
    specPath: { type: 'string', description: 'Path to the created/updated specification markdown file under docs/superpowers/specs/' },
    summary: { type: 'string', description: 'Brief summary of the architectural design' },
    testCases: {
      type: 'array',
      items: {
        type: 'object',
        required: ['id', 'description', 'assertionTarget', 'mutationBreakPoint'],
        properties: {
          id: { type: 'string', description: 'e.g. TC-1' },
          description: { type: 'string', description: 'What exact behavior is asserted' },
          assertionTarget: { type: 'string', description: 'The specific production file/method/property under test' },
          mutationBreakPoint: { type: 'string', description: 'The exact production code change that would cause this test to fail' }
        }
      }
    }
  }
}

const boardResult = await agent(
  `You are the Technical Architect.
TASK: ${TASK}

Your responsibility:
1. Investigate the codebase using read-only tools (search_graph, codegraph_explore, Read, etc.).
2. Write a comprehensive specification file under docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md.
3. The spec MUST contain a "## Test Cases (The Board)" section.
4. Each test case MUST specify:
   - What real behavior is asserted (no shallow existence tests, no framework-only assertions).
   - What exact production code change (mutation break point) would make it fail.
5. Do NOT modify any production code in src/!

Return the structured board output.`,
  {
    label: 'architect:board',
    phase: 'The Board',
    agentType: 'blatui-architect',
    schema: BOARD_SCHEMA,
    effort: 'high'
  }
)

if (!boardResult || !boardResult.testCases || boardResult.testCases.length === 0) {
  log('❌ Workflow halted: Architect failed to produce a valid Test Cases Board.')
  return { status: 'failed', stage: 'The Board', reason: 'No test cases defined' }
}

log(`✅ Board established with ${boardResult.testCases.length} test cases in ${boardResult.specPath}`)

// ---------------------------------------------------------------------------
// Phase 2: Red Light Check (Write tests ONLY and assert they FAIL)
// ---------------------------------------------------------------------------
phase('Red Light Check')
log('🔴 Phase 2: Writing tests and enforcing the Red Light Gate (tests MUST fail)...')

const RED_CHECK_SCHEMA = {
  type: 'object',
  required: ['isRed', 'failingTestsCount', 'testFiles', 'pestOutputSummary', 'diagnostics'],
  properties: {
    isRed: { type: 'boolean', description: 'True if and only if the newly written tests legitimately fail' },
    failingTestsCount: { type: 'number', description: 'Number of failing tests' },
    testFiles: { type: 'array', items: { type: 'string' }, description: 'Paths of test files written or modified' },
    pestOutputSummary: { type: 'string', description: 'Concise summary of failing test names and assertion failures' },
    diagnostics: { type: 'string', description: 'Confirmation that tests failed for the EXPECTED missing feature, not syntax/runtime errors' }
  }
}

const redResult = await agent(
  `You are tasked with writing the tests defined in the Test Cases Board and proving they fail.
Spec Path: ${boardResult.specPath}
Test Cases to implement:
${JSON.stringify(boardResult.testCases, null, 2)}

STRICT RULES:
1. Write/update test files under tests/ (e.g. tests/Feature/ or tests/Unit/) using Pest syntax.
2. DO NOT TOUCH ANY PRODUCTION CODE IN src/ OR database/migrations/!
3. Run the targeted tests using 'vendor/bin/pest <test-files>'.
4. VERIFY THAT THE TESTS FAIL (RED).
   - If they PASS immediately: they are useless change detectors or testing already existing framework behavior. Rewrite them so they test the intended new logic!
   - If they error due to typo/syntax: fix the test until it fails cleanly on the actual missing behavior.

Return the verification result.`,
  {
    label: 'test:write-and-verify-red',
    phase: 'Red Light Check',
    schema: RED_CHECK_SCHEMA,
    effort: 'high'
  }
)

if (!redResult || !redResult.isRed || redResult.failingTestsCount === 0) {
  log('❌ RED LIGHT CHECK FAILED: The written tests passed without production code changes or failed to register.')
  log('This violates the core SD-TDD invariant: No production code without a proven failing test first.')
  return {
    status: 'rejected',
    stage: 'Red Light Check',
    reason: 'Tests did not fail against current codebase. Likely testing framework behavior or trivial constants.',
    details: redResult
  }
}

log(`🔴 Red light confirmed: ${redResult.failingTestsCount} test(s) legitimately failing as expected.`)

// ---------------------------------------------------------------------------
// Phase 3: Implementation (Engineer writes minimal code to turn red to green)
// ---------------------------------------------------------------------------
phase('Implementation')
log('🟢 Phase 3: Dispatching Backend Engineer to implement production code (消消乐)...')

const IMPLEMENT_SCHEMA = {
  type: 'object',
  required: ['status', 'filesModified', 'targetTestsPassing', 'notes'],
  properties: {
    status: { type: 'string', enum: ['GREEN', 'BLOCKED'] },
    filesModified: { type: 'array', items: { type: 'string' } },
    targetTestsPassing: { type: 'boolean' },
    notes: { type: 'string' }
  }
}

const implementResult = await agent(
  `You are the Backend Engineer.
TASK: Implement the minimal production code to turn all failing tests GREEN.

Spec Path: ${boardResult.specPath}
Target Test Files: ${JSON.stringify(redResult.testFiles)}
Expected Behaviors to fulfill:
${JSON.stringify(boardResult.testCases, null, 2)}

CONSTRAINTS:
1. Write clean, idiomatic Laravel package code matching project conventions.
2. STRICTLY FORBIDDEN: Do NOT edit, weaken, or delete the test files in tests/! You must make the existing tests pass by writing production code in src/.
3. Run 'vendor/bin/pest ${redResult.testFiles.join(' ')}' until all target tests pass.

Return your implementation result.`,
  {
    label: 'engineer:implement',
    phase: 'Implementation',
    agentType: 'blatui-backend-engineer',
    schema: IMPLEMENT_SCHEMA,
    effort: 'high'
  }
)

if (!implementResult || implementResult.status !== 'GREEN' || !implementResult.targetTestsPassing) {
  log('❌ Implementation failed to turn target tests green. Routing back for architectural review.')
  return {
    status: 'failed',
    stage: 'Implementation',
    reason: 'Engineer could not satisfy tests with production code',
    details: implementResult
  }
}

log('🟢 Target tests successfully turned GREEN!')

// ---------------------------------------------------------------------------
// Phase 4: Gate Verification (Full repository check)
// ---------------------------------------------------------------------------
phase('Gate Verification')
log('🛡️ Phase 4: Running full repository quality gate (composer test)...')

const GATE_SCHEMA = {
  type: 'object',
  required: ['allGatesPassed', 'phpstanClean', 'pintClean', 'typeCoverage100', 'pestAllGreen', 'outputSummary'],
  properties: {
    allGatesPassed: { type: 'boolean' },
    phpstanClean: { type: 'boolean' },
    pintClean: { type: 'boolean' },
    typeCoverage100: { type: 'boolean' },
    pestAllGreen: { type: 'boolean' },
    outputSummary: { type: 'string' }
  }
}

const gateResult = await agent(
  `Run the full project gate: 'composer test' (or 'vendor/bin/phpstan analyse', 'vendor/bin/pint --test', 'composer test:types', 'vendor/bin/pest').
Verify:
1. PHPStan: 0 errors
2. Pint: clean code style
3. Type Coverage: 100%
4. Pest: 100% passing across the entire repository suite, no regressions.

If Pint finds styling issues, run 'vendor/bin/pint' to fix them automatically.
Return the complete gate verification result.`,
  {
    label: 'gate:composer-test',
    phase: 'Gate Verification',
    schema: GATE_SCHEMA,
    effort: 'medium'
  }
)

if (!gateResult || !gateResult.allGatesPassed) {
  log('⚠️ Full suite check surfaced issues. Review output summary.')
  return {
    status: 'needs_attention',
    stage: 'Gate Verification',
    targetTests: 'PASSED',
    fullSuite: gateResult
  }
}

log('🎉 SD-TDD Cycle Completed! All phases passed with 100% test coverage and pristine quality.')

return {
  status: 'success',
  specPath: boardResult.specPath,
  testCases: boardResult.testCases,
  filesModified: implementResult.filesModified,
  testFiles: redResult.testFiles,
  gateSummary: gateResult.outputSummary
}
