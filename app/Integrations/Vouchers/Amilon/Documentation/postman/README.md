# Amilon API - Postman Testing

Quick access to Postman testing documentation.

## 📖 Documentation

### Getting Started
→ **[GETTING_STARTED.md](GETTING_STARTED.md)** - Main user guide with quick start, testing workflows, and troubleshooting

### Technical Reference
→ **[TECHNICAL_REFERENCE.md](TECHNICAL_REFERENCE.md)** - Environment variables, service mapping, and advanced configuration

### API Documentation
→ **[API_CALLS_REPORT.md](../API_CALLS_REPORT.md)** - Complete technical report for Amilon developers (25K)

→ **[API_CATEGORIES_ENDPOINT_BEHAVIOR.md](../API_CATEGORIES_ENDPOINT_BEHAVIOR.md)** - Categories endpoint behavior details

## 🚀 Quick Start

1. Generate environments: `./generate-postman-env.sh all`
2. Import to Postman: `postman-collection.json` + environment files
3. Test: Run "Get Access Token" → Test endpoints

## 📦 Files

- `postman-collection.json` - Collection with 7 endpoints
- `postman-environment*.json` - Environment files (dev/staging/prod)
- `generate-postman-env.sh` - Environment generation script