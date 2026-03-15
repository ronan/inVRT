#!/bin/bash

echo "📸 Capturing references from '$INVRT_ENVIRONMENT' environment ($INVRT_URL) with profile: '$INVRT_PROFILE' and device: '$INVRT_DEVICE'"

node $INVRT_SCRIPTS_DIR'/backstop.js' reference