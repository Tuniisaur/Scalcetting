pipeline {
    agent any

    environment {
        APP_DIR = "/opt/scalcetting"
        BRANCH = "main"
    }

    stages {

        stage('Checkout') {
            steps {
                echo "Cloning branch ${BRANCH} in Jenkins workspace"
                checkout([$class: 'GitSCM',
                    branches: [[name: "refs/heads/${BRANCH}"]],
                    doGenerateSubmoduleConfigurations: false,
                    extensions: [],
                    userRemoteConfigs: [[url: 'https://github.com/Tuniisaur/Scalcetting.git']]
                ])
            }
        }

        stage('Deploy') {
            steps {
                echo "Copying files to ${APP_DIR}, excluding .git, uploads, and sessions"
                sh """
                    rsync -a --delete \
                        --exclude='.git' \
                        --exclude='uploads/' \
                        --exclude='sessions/' \
                        ${WORKSPACE}/ ${APP_DIR}/
                """
            }
        }

        stage('Permissions') {
            steps {
                echo "Setting permissions for ${APP_DIR} (excluding uploads and sessions)"
                sh """
                    find ${APP_DIR} -mindepth 1 \
                        -not -path '${APP_DIR}/uploads*' \
                        -not -path '${APP_DIR}/sessions*' \
                        -exec chown -R www-data:www-data {} +
                """
            }
        }
    }
}
