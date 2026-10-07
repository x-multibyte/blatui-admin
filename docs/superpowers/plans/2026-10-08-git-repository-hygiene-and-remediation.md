# Git 仓库净化与敏感文件过滤实施计划 (Git Repository Hygiene & Remediation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 彻底从本地与远程 Git 仓库（包括所有历史 Commit 与 Tag）中物理抹除 `.agents/`、`.claude`、`.mcp.json` 与 `skills-lock.json`，完善 `.gitignore` 规则，清空并吊销泄露的 API Token，同时 100% 保护本地开发工作区文件不受损毁。

**Architecture:** 采用「快照备份保护 -> 凭据本地脱敏 -> git-filter-repo 全历史物理抹除 -> 远端多分支/标签强制同步 -> 本地工作区完整性复原」的五阶段严格流水线。

**Tech Stack:** Git, git-filter-repo, Bash, Tar/Gzip.

**Spec:** `AGENTS.md` (Package Conventions & Clean Repository Contracts) & 用户对话决策确认记录。

## Global Constraints

- **本地文件保护原则**：本地磁盘上的 `.agents/`、本地技能与工作流数据必须 100% 完整保留，不可因 git 操作丢失。
- **扩展包依赖隔离原则**：`composer.lock` 必须永久被 `.gitignore` 忽略，不得提交到扩展包源码库。
- **历史抹除彻底性**：所有历史 Commit、Merge Commit 以及 Tag（如 `v0.0.1`）均需清除目标路径，不得有任何残留分支。
- **远端安全重写**：`git-filter-repo` 执行后必须正确重建 `origin` 远端并执行 `--force` 推送。
- **凭据脱敏前置**：在处理 Git 提交前，本地文件中的明文 Token 必须先清空，防止二次误提交。

## Review Focus

1. **工作区被 filter-repo 清空的恢复机制**：`git filter-repo` 在重写历史时会将工作树匹配路径删除，必须在 Task 4 中依赖 Task 1 的快照无损还原。
2. **Tag SHA 变动的覆盖推送**：`v0.0.1` 标签在历史重写后 SHA 改变，若不带 `--tags` 强推将导致远端 Tag 仍包含旧历史。
3. **远程分支存在遗留追踪**：除 `main` 之外的远端分支（如 `chore/gitignore-exclusions`, `feat/operation-log`）需一并清理或同步。
4. **Origin Remote 被自动剥离**：`git-filter-repo` 默认会删除 remote origin，必须有显式步骤重新关联 `git@github.com:x-multibyte/blatui-admin.git`。
5. **Git Index 与 .gitignore 规则生效确认**：重写并还原后，必须执行 `git status` 确认 working tree 为 clean，本地 `.agents/` 处于 untracked & ignored 状态。

---

### Task 1: 本地物理快照备份与敏感凭据治理

**Files:**
- Create: `/tmp/blatui-admin-backup-20261008.tar.gz`
- Modify: `.agents/settings.local.json`

**Interfaces:**
- Produces: 完整的工作区本地物理压缩包，脱敏后的 `.agents/settings.local.json`。

- [x] **Step 1: 创建本地完整工作区快照**

在执行任何不可逆的 Git 历史重写前，将当前工作区完整打包至临时目录：
```bash
tar -czf /tmp/blatui-admin-backup-20261008.tar.gz -C /laravel/packages/x-multibyte/blatui-admin .
```

- [x] **Step 2: 验证备份包完整性**

运行命令验证备份包大小及内部关键文件（`.agents/settings.local.json` 等）：
```bash
ls -lh /tmp/blatui-admin-backup-20261008.tar.gz
tar -tf /tmp/blatui-admin-backup-20261008.tar.gz | grep "settings.local.json"
```
预期结果：备份文件存在且包含 `.agents/settings.local.json`。

- [x] **Step 3: 脱敏 `.agents/settings.local.json` 中的 Anthropic Token**

将 `.agents/settings.local.json` 中的硬编码 Token 清空，防止本地文件后续被误读或二次提交：
```json
{
  "env": {
    "ANTHROPIC_AUTH_TOKEN": ""
  }
}
```

- [x] **Step 4: 记录安全提示：吊销泄露的 Token**

确认已提醒用户立即访问 Anthropic 控制台吊销 `sk-f06774bef620334d-zr9rfk-94b7a5e4`。

---

### Task 2: 锁定 .gitignore 排除规则基准

**Files:**
- Modify: `.gitignore`

**Interfaces:**
- Consumes: Task 1 备份
- Produces: 规则严密的 `.gitignore`，涵盖所有扩展包规范与 AI 本地工具。

- [x] **Step 1: 校验 .gitignore 中的规则清单**

确认 `.gitignore` 包含以下所有规则：
```gitignore
/vendor/
node_modules/
npm-debug.log
yarn-error.log

# Laravel & Composer Package
composer.lock
.env
.env.backup
.env.example
.phpunit.result.cache
.phpunit.cache/
Homestead.json
Homestead.yaml
.phpactor.json
.php-cs-fixer.cache
phpstan.neon.cache
rector.php.cache

# IDEs & System
.idea/
.vscode/
*.swp
*.swo
*~
.DS_Store
Thumbs.db
opencode.json
.mcp.json
.mcp.local.json

# Local Binaries & Tools
/bin/

# Agent & AI Tooling Caches / Settings
/.agents/
/.agent/
/.claude
/.claude/
/.codegraph/
/.gitnexus/
/.hermes/
/.superpowers/
skills-lock.json

# Package specific & Builds
/public/
/workbench/
/build/
```

- [x] **Step 2: 验证规则生效性**

运行测试检查 `composer.lock`、`bin/act`、`.agents/` 是否均被 git 判定为 ignored：
```bash
git check-ignore -v .agents bin/act composer.lock opencode.json .mcp.json
```
预期结果：每一项均匹配对应的 `.gitignore` 规则行。

---

### Task 3: 使用 git-filter-repo 彻底物理抹除 Git 历史

**Files:**
- Modify: Git 整个版本库历史对象（.git/objects）

**Interfaces:**
- Consumes: Task 1 备份
- Produces: 彻底不含 `.agents`、`.claude`、`.mcp.json`、`skills-lock.json` 的新 Git 提交树。

- [x] **Step 1: 检查并安装 git-filter-repo**

检查系统是否已安装 `git-filter-repo`，若未安装则通过 pip 安装：
```bash
which git-filter-repo || pip install git-filter-repo
```

- [x] **Step 2: 执行全历史物理过滤**

针对所有历史提交和标签，彻底剔除目标文件：
```bash
git filter-repo \
  --path .agents \
  --path .claude \
  --path .mcp.json \
  --path skills-lock.json \
  --invert-paths \
  --force
```

- [x] **Step 3: 重新添加 Git Remote 远端关联**

`git-filter-repo` 运行后会自动移除 remote 以防误推，需重新绑定：
```bash
git remote add origin git@github.com:x-multibyte/blatui-admin.git
```

- [x] **Step 4: 验证历史提交中彻底无残留**

执行 log 检索，确保所有历史中没有任何提及目标路径的提交：
```bash
git log --all -- .agents .claude .mcp.json skills-lock.json
```
预期结果：无任何输出（输出为空）。

---

### Task 4: 本地工作区工具与配置无损还原

**Files:**
- Restore: `.agents/`, `.claude`, `.mcp.json`

**Interfaces:**
- Consumes: Task 1 备份文件 `/tmp/blatui-admin-backup-20261008.tar.gz`
- Produces: 磁盘上完整保留的本地 Agent 环境与配置。

- [x] **Step 1: 检查本地磁盘并按需从快照精准还原**

检查工作区，如果 `git filter-repo` 将工作目录中的 `.agents` 删除了，则从快照中仅解压还原该目录：
```bash
if [ ! -d ".agents" ]; then
  tar -xzf /tmp/blatui-admin-backup-20261008.tar.gz .agents .claude .mcp.json
fi
```

- [x] **Step 2: 验证工作区 Git 状态**

运行 `git status` 确认当前工作树干净，且还原的本地文件已被 `.gitignore` 成功接管：
```bash
git status
```
预期结果：`nothing to commit, working tree clean`。

---

### Task 5: 强制同步推送到远端仓库与最终审计

**Files:**
- Remote: `origin/main`, `origin/v0.0.1`, 所有远端分支与标签

**Interfaces:**
- Consumes: Task 3 生成的新提交树
- Produces: 远端 GitHub 仓库彻底净化的 Git 状态。

- [x] **Step 1: 强制推送所有分支与标签至远端**

```bash
git push origin --force --all
git push origin --force --tags
```

- [x] **Step 2: 验证远端分支与标签状态**

检查远端引用与最新 commit 信息：
```bash
git log -n 5 --oneline
git branch -vv
```

- [x] **Step 3: 清理临时备份（可选，或保留至确认无误后再删）**

确认本地与远端一切就绪后，保留 `/tmp/blatui-admin-backup-20261008.tar.gz` 7天后自动清理或手动移除。
