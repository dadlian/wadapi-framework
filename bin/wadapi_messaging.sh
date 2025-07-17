#!/bin/bash

# Start RabbitMQ Subscriber script
php /var/www/html/wadapi/messaging.php >> /wadapi.log 2>&1 &

# Get the PID of the process
pid=$!

# Monitor the process
while true; do
    # Check if the process is still running
    if ps ax -o pid | grep $pid > /dev/null; then
        # Process is running, wait for a while
        sleep 1
    else
        # Process has died, restart it
        echo "Reconnecting to RabbitMQ"

		# Start RabbitMQ Subscriber script
		php /var/www/html/wadapi/messaging.php >> /wadapi.log 2>&1 &
        
        pid=$!
    fi
done

