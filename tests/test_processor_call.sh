#!/bin/bash
echo "=== Testing Processor Call ==="
echo ""

# Clear logs
> ../var/log/dev.log
> ../var/log/processor_calls.log

echo "1. Making API request..."
curl -X POST http://localhost:7071/api/password-reset-request \
  -H "Content-Type: application/json" \
  -d '{"email":"elie.satouf@gmail.com"}'

echo ""
echo ""
echo "2. Checking logs..."
echo "   Processor log:"
cat ../var/log/processor_calls.log 2>/dev/null || echo "   No processor log found"
echo ""
echo "   Recent dev.log entries:"
tail -20 ../var/log/dev.log | grep -i "processor\|PasswordResetRequest"
