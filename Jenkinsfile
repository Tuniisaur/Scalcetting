pipeline {
    agent any

    environment {
        APP_DIR = "/opt/scalcetting"
        BRANCH = "main"
    }

    stages {

        stage('Deploy') {
            steps {
                echo "Deploying branch ${BRANCH} to ${APP_DIR}"
                sh """
                    cd ${APP_DIR}
                    git fetch --all
                    git reset --hard origin/${BRANCH}
                """
            }
        }

        stage('Permissions') {
            steps {
                echo "Setting permissions for ${APP_DIR}"
                sh "chown -R www-data:www-data ${APP_DIR}"
            }
        }
    }
}
