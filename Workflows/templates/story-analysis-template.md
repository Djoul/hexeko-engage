# Story Analysis: [STORY-KEY] - [Title]

## 📋 Story Information
- **Type**: Story
- **Epic**: [EPIC-KEY if applicable]
- **Priority**: [High/Medium/Low]
- **Status**: Analysis
- **Assignee**: [Developer]

## 🎯 Acceptance Criteria
Extract from Jira or define here:
1. [ ] Criteria 1
2. [ ] Criteria 2
3. [ ] Criteria 3
4. [ ] Criteria 4

## 🔍 Technical Analysis

### Current System State
- **Existing Code**: [Files/modules affected]
- **Database Impact**: [Tables/migrations needed]
- **API Changes**: [Endpoints affected]
- **Dependencies**: [External services/libraries]

### Proposed Solution

#### Approach
[Describe the technical approach]

#### Components Needed
- **Services**: 
  - [ ] Service1
  - [ ] Service2
- **Actions**:
  - [ ] Action1
  - [ ] Action2
- **Controllers**:
  - [ ] Controller1
- **DTOs**:
  - [ ] DTO1
  - [ ] DTO2

#### Database Changes
```sql
-- Migrations needed
```

#### API Design
```yaml
Endpoint: [METHOD] /api/v1/[resource]
Request:
  Headers:
    - Authorization: Bearer {token}
  Body:
    - field1: type
    - field2: type
Response:
  200:
    - data: object
  400:
    - error: string
```

## ⚠️ Risks & Considerations

### Technical Risks
1. **Risk 1**: [Description]
   - **Impact**: [High/Medium/Low]
   - **Mitigation**: [Strategy]

2. **Risk 2**: [Description]
   - **Impact**: [High/Medium/Low]
   - **Mitigation**: [Strategy]

### Dependencies
- **Blocker**: [What blocks this story]
- **Blocks**: [What this story blocks]
- **Related**: [Related stories]

## 🧪 Test Strategy

### Unit Tests
- [ ] Service layer tests
- [ ] Action tests
- [ ] DTO validation tests

### Integration Tests
- [ ] API endpoint tests
- [ ] Database transaction tests
- [ ] External service integration

### E2E Tests
- [ ] Complete user flow
- [ ] Error scenarios

## 📊 Estimation

### Complexity
- **Technical**: [Simple/Medium/Complex]
- **Business**: [Simple/Medium/Complex]
- **Overall**: [Simple/Standard/Complex]

### Phases Required
- [ ] Discovery (completed)
- [ ] Analysis (in progress)
- [ ] Design
- [ ] TDD
- [ ] Implementation
- [ ] Validation
- [ ] Documentation

## 📝 Notes
[Any additional notes, decisions, or considerations]

## 🔗 References
- Jira: [Link]
- Confluence: [Link]
- Related PRs: [Links]
- Documentation: [Links]

---
*Analysis completed on: [Date]*
*Analyst: [Name]*