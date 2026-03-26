pipeline {
    agent any

    environment {
        APP_DIR = '/opt/scalcetting'
    }

    stages {

        stage('Checkout SCM') {
            steps {
                checkout scm
            }
        }

        stage('Deploy') {
            steps {
                echo "Copying files to ${APP_DIR}, excluding .git, uploads, and sessions"
                sh """
                    sudo rsync -a --delete \
                        --exclude='.git' \
                        --exclude='.env' \
                        --exclude='uploads/' \
                        --exclude='sessions/' \
                        --chown=www-data:www-data \
                        ${WORKSPACE}/ ${APP_DIR}/
                """
            }
        }

        stage('Set Permissions') {
            steps {
                echo "Setting permissions for ${APP_DIR} (excluding uploads, sessions, and .git)"
                sh """
                    sudo find ${APP_DIR} -mindepth 1 \
                        -not -path "${APP_DIR}/uploads*" \
                        -not -path "${APP_DIR}/sessions*" \
                        -not -path "${APP_DIR}/.git*" \
                        -exec chmod 755 {} +
                """
            }
        }
    }

    post {
        success {
            echo 'Deployment completed successfully!'
        }
        failure {
            echo 'Deployment failed!'
        }
    }
}
