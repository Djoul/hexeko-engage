# Guide d'Intégration MCP Servers

## 🎯 Vue d'Ensemble

Ce guide détaille l'utilisation des serveurs MCP (Model Context Protocol) pour automatiser et enrichir le workflow de développement backend. Quatre serveurs principaux sont disponibles pour optimiser le processus.

## 🔌 Serveurs MCP Disponibles

### 1. Full Productivity Server

#### Capacités
- **Jira** : Gestion complète des issues, epics, stories
- **Todoist** : Synchronisation des tâches personnelles
- **Sentry** : Analyse et tracking des erreurs
- **Confluence** : Publication automatique de documentation
- **Notion** : Base de connaissances et templates

#### Configuration Requise
```yaml
mcp_server: full-productivity-server
endpoints:
  jira: https://[your-domain].atlassian.net
  sentry: https://sentry.io/organizations/[org]
  confluence: https://[your-domain].atlassian.net/wiki
credentials: Configurées dans MCP
```

### 2. Context7

#### Capacités
- Documentation des librairies et frameworks
- Vérification de compatibilité des versions
- Exemples de code officiels
- Best practices et patterns
- Migration guides

#### Utilisation
```yaml
mcp_server: context7
usage:
  - Recherche: "Laravel Queue documentation"
  - Version: "PHP 8.3 compatibility"
  - Examples: "Stripe webhook implementation"
```

### 3. Firecrawl

#### Capacités
- Web scraping intelligent
- Extraction de documentation externe
- Analyse de sites concurrents
- Recherche de solutions
- Veille technologique

#### Utilisation
```yaml
mcp_server: firecrawl
capabilities:
  - scrape: Extract content from URLs
  - search: Find relevant information
  - map: Discover site structure
  - crawl: Deep site analysis
```

### 4. IDE Integration

#### Capacités
- Diagnostics en temps réel
- Navigation dans le code
- Refactoring automatique
- Linting et formatting

## 📋 Opérations MCP par Phase

### Phase DISCOVERY

#### Jira Operations
```javascript
// Récupérer une issue
mcp.jira_get_issue({
  issue_key: "UE-268"
})

// Récupérer un epic avec ses stories
mcp.jira_get_epic_issues({
  epic_key: "UE-250"
})

// Rechercher des issues
mcp.jira_search_issues({
  jql: "project = UE AND type = Story AND status = 'To Do'",
  max_results: 50
})
```

#### Todoist Operations
```javascript
// Lister les projets
mcp.todoist_list_projects()

// Récupérer les tâches d'un projet
mcp.todoist_list_tasks({
  project_id: "backend_tasks"
})

// Récupérer une tâche spécifique
mcp.todoist_get_task({
  task_id: "12345"
})
```

#### Sentry Operations
```javascript
// Lister les erreurs récentes
mcp.sentry_list_issues({
  project_slug: "backend-api",
  query: "is:unresolved",
  limit: 20
})

// Détails d'une erreur
mcp.sentry_get_issue({
  issue_id: "98765"
})

// Events d'une erreur
mcp.sentry_list_issue_events({
  issue_id: "98765",
  limit: 10
})
```

### Phase ANALYSIS

#### Context7 Operations
```javascript
// Rechercher documentation Laravel
mcp.context7_resolve_library_id({
  libraryName: "Laravel"
})

// Obtenir documentation spécifique
mcp.context7_get_library_docs({
  context7CompatibleLibraryID: "/laravel/framework",
  topic: "queues",
  tokens: 10000
})
```

#### Firecrawl Operations
```javascript
// Scraper une page de documentation
mcp.firecrawl_scrape({
  url: "https://stripe.com/docs/webhooks",
  formats: ["markdown"],
  onlyMainContent: true
})

// Rechercher des informations
mcp.firecrawl_search({
  query: "PHP payment gateway integration best practices",
  limit: 5,
  scrapeOptions: {
    formats: ["markdown"],
    onlyMainContent: true
  }
})

// Mapper un site
mcp.firecrawl_map({
  url: "https://docs.example.com",
  limit: 100,
  includeSubdomains: false
})
```

### Phase DESIGN

#### Confluence Search
```javascript
// Rechercher documentation existante
mcp.confluence_search_pages({
  query: "payment architecture",
  space_key: "TECH"
})

// Récupérer une page spécifique
mcp.confluence_get_page({
  page_id: "123456"
})
```

#### Notion Templates
```javascript
// Rechercher templates
mcp.notion_search({
  query: "API design template"
})

// Récupérer un template
mcp.notion_get_page({
  page_id: "template-123"
})
```

### Phase IMPLEMENTATION

#### Jira Updates
```javascript
// Créer sous-tâches techniques
mcp.jira_create_issue({
  project_key: "UE",
  issue_type: "Sub-task",
  summary: "Implement StripeService",
  description: "Service layer for Stripe integration",
  parent_id: "UE-268"
})

// Mettre à jour le statut
mcp.jira_transition_issue({
  issue_key: "UE-268",
  transition_id: "31", // "In Progress"
  comment: "Starting implementation"
})
```

#### IDE Diagnostics
```javascript
// Obtenir les diagnostics
mcp.ide_getDiagnostics({
  uri: "file:///path/to/file.php"
})
```

### Phase VALIDATION

#### Sentry Monitoring
```javascript
// Vérifier nouvelles erreurs
mcp.sentry_list_issues({
  project_slug: "backend-api",
  query: "first_seen:>now-1h"
})

// Marquer comme résolu
mcp.sentry_update_issue({
  issue_id: "98765",
  status: "resolved"
})
```

### Phase DOCUMENTATION

#### Confluence Publishing
```javascript
// Créer page de documentation
mcp.confluence_create_page({
  space_key: "TECH",
  title: "WellWo API Integration Guide",
  body: markdownContent,
  parent_id: "789456"
})

// Mettre à jour une page
mcp.confluence_update_page({
  page_id: "123456",
  title: "Updated API Guide",
  body: updatedContent
})

// Ajouter des labels
mcp.confluence_add_page_label({
  page_id: "123456",
  label: "api-documentation"
})
```

#### Notion Documentation
```javascript
// Créer page de documentation
mcp.notion_create_page({
  parent_id: "workspace-123",
  properties: {
    title: "API Endpoint Documentation",
    tags: ["backend", "api", "rest"]
  },
  children: documentationBlocks
})
```

## 🤖 Automatisation Complète

### Workflow Epic Automatisé
```javascript
async function processEpic(epicKey) {
  // 1. Fetch epic details
  const epic = await mcp.jira_get_epic_issues({ epic_key: epicKey });
  
  // 2. Create workspace structure
  await createEpicWorkspace(epic);
  
  // 3. Analyze each story
  for (const story of epic.stories) {
    // Get story details
    const details = await mcp.jira_get_issue({ issue_key: story.key });
    
    // Search for relevant documentation
    if (story.requiresIntegration) {
      const docs = await mcp.firecrawl_search({
        query: story.integrationKeywords,
        limit: 5
      });
      await saveDocumentation(story.key, docs);
    }
    
    // Create technical sub-tasks
    const subtasks = generateSubtasks(story);
    for (const subtask of subtasks) {
      await mcp.jira_create_issue(subtask);
    }
  }
  
  // 4. Generate and publish documentation
  const epicDoc = generateEpicDocumentation(epic);
  await mcp.confluence_create_page({
    space_key: "TECH",
    title: `${epicKey} - Technical Documentation`,
    body: epicDoc
  });
}
```

### Bug Fix Automatisé
```javascript
async function processSentryBug(sentryId) {
  // 1. Get error details
  const error = await mcp.sentry_get_issue({ issue_id: sentryId });
  const events = await mcp.sentry_list_issue_events({ 
    issue_id: sentryId, 
    limit: 5 
  });
  
  // 2. Create Jira bug
  const jiraBug = await mcp.jira_create_issue({
    project_key: "UE",
    issue_type: "Bug",
    summary: `[Sentry ${sentryId}] ${error.title}`,
    description: formatSentryError(error, events),
    priority: mapSentryPriority(error.level)
  });
  
  // 3. Search for similar solutions
  const solutions = await mcp.firecrawl_search({
    query: `${error.type} ${error.message} solution`,
    limit: 3
  });
  
  // 4. Create fix documentation
  await createBugFixDoc(jiraBug.key, error, solutions);
  
  // 5. After fix, update both systems
  await mcp.sentry_update_issue({
    issue_id: sentryId,
    status: "resolved"
  });
  
  await mcp.jira_transition_issue({
    issue_key: jiraBug.key,
    transition_id: "51", // "Done"
    comment: `Fixed and deployed. Sentry issue ${sentryId} resolved.`
  });
}
```

## 📊 Cas d'Usage Avancés

### 1. Research avec Context7 + Firecrawl
```javascript
async function researchTechnology(topic) {
  // Official documentation via Context7
  const libraryId = await mcp.context7_resolve_library_id({
    libraryName: topic
  });
  
  const officialDocs = await mcp.context7_get_library_docs({
    context7CompatibleLibraryID: libraryId,
    tokens: 15000
  });
  
  // External resources via Firecrawl
  const externalDocs = await mcp.firecrawl_search({
    query: `${topic} best practices implementation guide`,
    limit: 10
  });
  
  // Deep dive on specific pages
  const detailedDocs = await Promise.all(
    externalDocs.results.map(result => 
      mcp.firecrawl_scrape({
        url: result.url,
        formats: ["markdown"],
        onlyMainContent: true
      })
    )
  );
  
  return {
    official: officialDocs,
    external: externalDocs,
    detailed: detailedDocs
  };
}
```

### 2. Documentation Chain
```javascript
async function createCompleteDocs(storyKey) {
  // 1. Get story details from Jira
  const story = await mcp.jira_get_issue({ issue_key: storyKey });
  
  // 2. Generate API documentation
  const apiDocs = generateApiDocs(story);
  
  // 3. Create Confluence page
  const confluencePage = await mcp.confluence_create_page({
    space_key: "API",
    title: `${storyKey} - API Documentation`,
    body: apiDocs
  });
  
  // 4. Create Notion guide
  const notionPage = await mcp.notion_create_page({
    parent_id: "api-guides",
    properties: {
      title: `Frontend Integration - ${storyKey}`,
      jira: storyKey,
      confluence: confluencePage.url
    },
    children: createNotionBlocks(apiDocs)
  });
  
  // 5. Update Jira with links
  await mcp.jira_update_issue({
    issue_key: storyKey,
    description: story.description + 
      `\n\nDocumentation:\n` +
      `- Confluence: ${confluencePage.url}\n` +
      `- Notion: ${notionPage.url}`
  });
}
```

### 3. Monitoring Pipeline
```javascript
async function setupMonitoring(featureKey) {
  // 1. Create Sentry alerts
  const sentryProject = "backend-api";
  
  // 2. Create dashboard in Confluence
  const dashboard = await mcp.confluence_create_page({
    space_key: "OPS",
    title: `${featureKey} - Monitoring Dashboard`,
    body: generateDashboardTemplate(featureKey)
  });
  
  // 3. Setup Todoist reminders
  await mcp.todoist_create_task({
    content: `Review ${featureKey} metrics`,
    due_string: "every week",
    project_id: "monitoring"
  });
  
  // 4. Document in Notion
  await mcp.notion_create_page({
    parent_id: "monitoring-guides",
    properties: {
      title: `${featureKey} Monitoring Setup`,
      alerts: sentryProject,
      dashboard: dashboard.url
    }
  });
}
```

## 🔧 Scripts MCP Intégrés

### init-with-mcp.sh
```bash
#!/bin/bash
# Initialize feature with full MCP integration

IDENTIFIER=$1
TYPE=$(./detect-type.sh $IDENTIFIER)

case $TYPE in
  "jira-epic")
    claude-mcp exec "processEpic('$IDENTIFIER')"
    ;;
  "jira-story")
    claude-mcp exec "processStory('$IDENTIFIER')"
    ;;
  "sentry-error")
    claude-mcp exec "processSentryBug('$IDENTIFIER')"
    ;;
  *)
    echo "Unknown type: $TYPE"
    ;;
esac
```

### sync-progress.sh
```bash
#!/bin/bash
# Sync progress across all MCP services

STORY_KEY=$1

# Update Jira
claude-mcp jira transition $STORY_KEY "In Progress"

# Update Todoist
claude-mcp todoist update-task --story=$STORY_KEY --status=active

# Check Sentry for related errors
claude-mcp sentry check-errors --tag=story:$STORY_KEY
```

## 📈 Métriques et Reporting

### Collecte Automatique
```javascript
async function collectMetrics(epicKey) {
  // Jira metrics
  const jiraStats = await mcp.jira_search_issues({
    jql: `"Epic Link" = ${epicKey}`,
    fields: ["status", "created", "resolved", "timespent"]
  });
  
  // Sentry errors
  const sentryErrors = await mcp.sentry_list_issues({
    query: `tag:epic:${epicKey}`,
    statsPeriod: "30d"
  });
  
  // Generate report
  const report = generateMetricsReport(jiraStats, sentryErrors);
  
  // Publish to Confluence
  await mcp.confluence_create_page({
    space_key: "METRICS",
    title: `${epicKey} - Metrics Report`,
    body: report
  });
}
```

## 🚨 Troubleshooting MCP

### Erreurs Communes

#### Jira Connection
```yaml
Erreur: "Jira API rate limit exceeded"
Solution:
  - Implémenter retry avec backoff
  - Cacher les résultats localement
  - Batch les requêtes
```

#### Context7 Limits
```yaml
Erreur: "Token limit exceeded"
Solution:
  - Réduire le paramètre tokens
  - Faire plusieurs requêtes ciblées
  - Utiliser topic pour filtrer
```

#### Firecrawl Timeout
```yaml
Erreur: "Crawl timeout"
Solution:
  - Utiliser scrape au lieu de crawl
  - Limiter la profondeur
  - Cibler des pages spécifiques
```

## 🎓 Best Practices MCP

### DO's ✅
- **Cacher** les résultats pour éviter les rate limits
- **Paralléliser** les requêtes indépendantes
- **Valider** les réponses avant utilisation
- **Logger** toutes les opérations MCP
- **Gérer** les erreurs gracieusement

### DON'Ts ❌
- **Ne pas** faire de requêtes en boucle serrée
- **Ne pas** ignorer les rate limits
- **Ne pas** stocker les credentials en clair
- **Ne pas** exposer les données sensibles
- **Ne pas** bypasser les validations

---

*Guide MCP Integration v1.0*
*Automatisation complète du workflow de développement*